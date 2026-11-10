<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Band;
use App\Integration\Orfeo\Exception\OrfeoUnavailable;
use App\Integration\Orfeo\OrfeoConfiguration;
use App\Integration\Orfeo\OrfeoGateway;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:orfeo:check', description: 'Vérifie la lecture des concerts Orfeo sans rien importer ni afficher la clé.')]
final class CheckOrfeoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrfeoConfiguration $configuration,
        private readonly OrfeoGateway $gateway,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('band', InputArgument::OPTIONAL, 'Identifiant du groupe dans BB.', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getArgument('band');
        if (!is_string($id) || 1 !== preg_match('/^[1-9][0-9]{0,8}$/D', $id)) {
            $io->error('Indique un identifiant de groupe valide.');

            return Command::INVALID;
        }
        $band = $this->em->find(Band::class, (int) $id);
        if (!$band || !$this->configuration->isConfiguredFor($band)) {
            $io->error('Vérifie les réglages ORFEO du fichier d’environnement privé.');

            return Command::INVALID;
        }
        try {
            $result = $this->gateway->events();
        } catch (OrfeoUnavailable $error) {
            $io->error($error->getMessage());

            return Command::FAILURE;
        }
        $confirmed = $cancelled = $contacts = 0;
        foreach ($result->events as $event) {
            $confirmed += $event->confirmed ? 1 : 0;
            $cancelled += $event->cancelled ? 1 : 0;
            $contacts += count($event->contacts);
        }
        $io->success('Connexion Orfeo disponible pour '.$band->getName().'.');
        $io->table(['Représentations', 'Confirmées', 'Annulations signalées', 'Contacts proposés', 'Projets écartés'], [[
            (string) count($result->events), (string) $confirmed, (string) $cancelled,
            (string) $contacts, (string) $result->ignored,
        ]]);
        $io->text('Aucun concert enregistré et aucune donnée modifiée dans Orfeo.');

        return Command::SUCCESS;
    }
}
