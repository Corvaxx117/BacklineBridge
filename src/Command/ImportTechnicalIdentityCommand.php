<?php

declare(strict_types=1);

namespace App\Command;

use App\Dto\Technical\TechnicalIdentity;
use App\Entity\Band;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:rider:import', description: 'Importe le style et les contacts techniques partageables depuis un fichier privé.')]
final class ImportTechnicalIdentityCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Fichier JSON privé.');
        $this->addArgument('band', InputArgument::OPTIONAL, 'Identifiant du groupe.', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = $input->getArgument('file');
        $id = filter_var($input->getArgument('band'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_string($path) || !is_file($path) || !is_readable($path) || filesize($path) > 16384 || false === $id) {
            $io->error('Indique un fichier JSON lisible de 16 Ko maximum et un groupe valide.');

            return Command::INVALID;
        }
        $band = $this->em->find(Band::class, $id);
        if (null === $band) {
            $io->error('Ce groupe est introuvable.');

            return Command::INVALID;
        }
        try {
            $data = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new \DomainException('Le fichier doit contenir les informations de la fiche technique.');
            }
            $identity = TechnicalIdentity::fromArray($data);
        } catch (\JsonException|\DomainException $error) {
            $io->error($error instanceof \JsonException ? 'Le fichier JSON est invalide.' : $error->getMessage());

            return Command::INVALID;
        }
        $band->setTechnicalIdentity($identity);
        $this->em->flush();
        $io->success(sprintf('Fiche technique enregistrée : %d contact(s) partageable(s).', count($identity->contacts)));
        $io->text('Les coordonnées restent dans la base et dans les prochains dossiers publiés.');

        return Command::SUCCESS;
    }
}
