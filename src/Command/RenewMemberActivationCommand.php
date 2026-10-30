<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\MemberProfile;
use App\Service\Member\AccountActivator;
use App\Service\Member\ActivationCodeWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:member:renew-activation',
    description: 'Renouvelle un code personnel pour un compte qui n’a jamais été activé.',
),]
final class RenewMemberActivationCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountActivator $activator,
        private readonly ActivationCodeWriter $writer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'profile',
            InputArgument::REQUIRED,
            'Identifiant public du profil, visible dans son URL.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $profile = $this->em
            ->getRepository(MemberProfile::class)
            ->findOneBy(['publicId' => $input->getArgument('profile')]);
        if (
            null === $profile
            || null === $profile->getUser()
            || $profile->getUser()->isActivated()
        ) {
            $io->error('Un profil avec un compte non activé est nécessaire.');

            return Command::INVALID;
        }
        $code = $this->activator->issue($profile);
        $path = $this->writer->write([
            [
                'name' => $profile->getPublicName(),
                'code' => $code,
                'expires' => (
                    $profile->getActivationExpiresAt() ??
                    throw new \LogicException('Le code doit avoir une date d’expiration.')
                )->format('d/m/Y'),
            ],
        ]);
        try {
            $this->em->flush();
        } catch (\Throwable $error) {
            unlink($path);
            throw $error;
        }
        $io->success('Nouveau code enregistré. Le précédent ne fonctionne plus.');
        $io->text('Fichier privé : '.$path);

        return Command::SUCCESS;
    }
}
