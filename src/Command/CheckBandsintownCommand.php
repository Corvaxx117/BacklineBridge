<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Band;
use App\Integration\Bandsintown\BandsintownConfiguration;
use App\Integration\Bandsintown\BandsintownGateway;
use App\Integration\Bandsintown\Dto\BandsintownEvent;
use App\Integration\Bandsintown\Exception\BandsintownUnavailable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:bandsintown:check',
    description: 'Vérifie la connexion Bandsintown sans importer de concert ni afficher la clé.',
)]
final class CheckBandsintownCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BandsintownConfiguration $configuration,
        private readonly BandsintownGateway $gateway,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('band', InputArgument::OPTIONAL, 'Identifiant du groupe local.', '1');
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
        if (null === $band || !$this->configuration->isConfiguredFor($band)) {
            $io->error(
                'Vérifie le groupe et les trois réglages BANDSINTOWN '.
                'dans le fichier d’environnement privé.',
            );

            return Command::INVALID;
        }
        try {
            $result = $this->gateway->events();
        } catch (BandsintownUnavailable $error) {
            $io->error($error->getMessage());

            return Command::FAILURE;
        }
        $today = $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d');
        $upcoming = count(array_filter(
            $result->events,
            static fn (BandsintownEvent $event): bool => $event->date >= $today,
        ));
        $io->success('Connexion Bandsintown disponible pour '.$band->getName().'.');
        $io->table(['Annonces disponibles', 'À venir', 'Écartées'], [[
            (string) count($result->events),
            (string) $upcoming,
            (string) $result->ignored,
        ]]);
        $io->text('Aucun concert enregistré. Pour choisir les dates : Concerts → Importer Bandsintown.');

        return Command::SUCCESS;
    }
}
