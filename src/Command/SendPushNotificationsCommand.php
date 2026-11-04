<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Notification\Push\PushConfiguration;
use App\Service\Notification\Push\PushSender;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:notifications:send', description: 'Envoie les notifications en attente, puis s’arrête.')]
final class SendPushNotificationsCommand extends Command
{
    public function __construct(
        private readonly PushSender $sender,
        private readonly PushConfiguration $configuration,
        #[Autowire('%kernel.project_dir%/var/private/web-push')] private readonly string $directory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximal d’envois par passage.', '30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->configuration->available()) {
            $io->error('Les notifications push sont désactivées ou leurs clés manquent.');

            return Command::INVALID;
        }
        $limit = $input->getOption('limit');
        if (!is_string($limit) || !ctype_digit($limit) || (int) $limit < 1 || (int) $limit > 100) {
            $io->error('La limite doit être comprise entre 1 et 100.');

            return Command::INVALID;
        }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Le dossier privé des notifications est inaccessible.');
        }
        $lock = fopen($this->directory.'/sender.lock', 'c');
        if (false === $lock) {
            throw new \RuntimeException('Le verrou d’envoi est inaccessible.');
        }
        try {
            // Un seul processus sur cette installation. Pas de transaction ouverte pendant le réseau.
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                $io->text('Un envoi est déjà en cours.');

                return Command::SUCCESS;
            }
            $counts = $this->sender->run((int) $limit);
            $io->text(sprintf(
                'Acceptés par le relais : %d ; à réessayer : %d ; annulés : %d ; échecs : %d.',
                $counts['sent'], $counts['deferred'], $counts['cancelled'], $counts['failed'],
            ));

            return $counts['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
