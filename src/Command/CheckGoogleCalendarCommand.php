<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Band;
use App\Integration\GoogleCalendar\Exception\GoogleCalendarUnavailable;
use App\Service\Calendar\CalendarReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:google-agenda:verifier', description: 'Vérifie la lecture de l’agenda partagé sans ajouter d’événement.')]
final class CheckGoogleCalendarCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CalendarReader $reader,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('groupe', InputArgument::REQUIRED, 'Identifiant du groupe dans BacklineBridge');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getArgument('groupe');
        if (!is_string($id) || 1 !== preg_match('/^[1-9][0-9]*$/D', $id)) {
            $io->error('Indique l’identifiant numérique du groupe.');

            return Command::INVALID;
        }
        $band = $this->entityManager->find(Band::class, (int) $id);
        if (null === $band) {
            $io->error('Ce groupe est introuvable.');

            return Command::INVALID;
        }
        try {
            $this->reader->invalidate();
            $listing = $this->reader->upcoming($band);
            $io->success(sprintf('Connexion réussie : %d événement(s) sur les 90 prochains jours. Aucun ajout effectué.', count($listing->events)));

            return Command::SUCCESS;
        } catch (GoogleCalendarUnavailable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }
}
