<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Notification\Push\PushConfiguration;
use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:notifications:keys', description: 'Crée une seule fois les clés privées des notifications push.')]
final class CreatePushKeysCommand extends Command
{
    public function __construct(private readonly PushConfiguration $configuration)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('subject', null, InputOption::VALUE_REQUIRED, 'Adresse HTTPS de cette installation.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $subject = $input->getOption('subject');
        if (!is_string($subject) || false === filter_var($subject, FILTER_VALIDATE_URL)
            || 'https' !== parse_url($subject, PHP_URL_SCHEME)
            || null !== parse_url($subject, PHP_URL_USER) || null !== parse_url($subject, PHP_URL_FRAGMENT)
        ) {
            $io->error('Indique l’adresse HTTPS du site avec --subject=https://adresse-du-site.');

            return Command::INVALID;
        }
        $path = $this->configuration->keyFile();
        if (file_exists($path)) {
            $this->configuration->credentials();
            $io->text('Les clés existent déjà. Elles sont conservées.');

            return Command::SUCCESS;
        }
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le dossier privé.');
        }
        $oldMask = umask(0077);
        try {
            $file = fopen($path, 'x');
            if (false === $file) {
                throw new \RuntimeException('Impossible de créer le fichier de clés.');
            }
            try {
                $contents = json_encode(['subject' => $subject, ...VAPID::createVapidKeys()], JSON_THROW_ON_ERROR);
                if (strlen($contents) !== fwrite($file, $contents)) {
                    throw new \RuntimeException('Le fichier de clés est incomplet.');
                }
            } finally {
                fclose($file);
            }
        } finally {
            umask($oldMask);
        }
        $io->success('Clés créées dans le dossier privé. Sauvegarde-les sans les publier dans Git.');

        return Command::SUCCESS;
    }
}
