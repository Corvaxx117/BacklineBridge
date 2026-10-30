<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Band;
use App\Enum\BandRole;
use App\Service\Security\UserCreator;
use App\Validator\AccountPassword;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsCommand(
    name: 'app:user:create',
    description: 'Crée un compte et son accès à un groupe, avec saisie masquée du mot de passe.',
),]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserCreator $creator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'band',
            null,
            InputOption::VALUE_REQUIRED,
            'Identifiant du groupe (obligatoire s’il y en a plusieurs).',
        )->addOption(
            'role',
            null,
            InputOption::VALUE_REQUIRED,
            'manager, booker, editor ou viewer.',
            BandRole::Viewer->value,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$input->isInteractive()) {
            $io->error(
                'Cette commande exige un terminal interactif pour saisir le mot de passe sans l’exposer dans les arguments.',
            );

            return Command::INVALID;
        }
        $role = BandRole::tryFrom((string) $input->getOption('role'));
        if (null === $role) {
            $io->error('Choisis manager, booker, editor ou viewer.');

            return Command::INVALID;
        }
        $bands = $this->entityManager->getRepository(Band::class);
        $bandOption = $input->getOption('band');
        $band =
            null !== $bandOption
                ? $bands->find($bandOption)
                : (1 === $bands->count([])
                    ? $bands->findOneBy([])
                    : null);
        if (!$band instanceof Band) {
            $io->error(
                'Précise un groupe existant avec --band. Sur une base vide, lance d’abord composer setup.',
            );

            return Command::INVALID;
        }

        $io->text(
            sprintf(
                'Groupe : %s (#%d) — accès : %s',
                $band->getName(),
                $band->getId(),
                $role->value,
            ),
        );
        $email = (string) $io->ask('Adresse e-mail');
        $name = (string) $io->ask('Nom affiché');
        $passwordQuestion = new Question('Mot de passe — '.AccountPassword::HELP)
            ->setHidden(true)
            ->setHiddenFallback(false);
        $confirmationQuestion = new Question('Confirmer le mot de passe')
            ->setHidden(true)
            ->setHiddenFallback(false);
        $password = (string) $io->askQuestion($passwordQuestion);
        $confirmation = (string) $io->askQuestion($confirmationQuestion);
        if ($password !== $confirmation) {
            $io->error('Les mots de passe ne correspondent pas. Aucun compte n’a été créé.');

            return Command::INVALID;
        }

        try {
            $user = $this->creator->create($email, $name, $password, $band, $role);
        } catch (ValidationFailedException $exception) {
            foreach ($exception->getViolations() as $violation) {
                $io->error($violation->getPropertyPath().': '.$violation->getMessage());
            }

            return Command::INVALID;
        } catch (UniqueConstraintViolationException) {
            $io->error('Un compte utilise déjà cette adresse. Aucun nouvel accès n’a été créé.');

            return Command::FAILURE;
        }

        $io->success(
            sprintf(
                'Compte %s créé pour %s. Connexion disponible sur /connexion.',
                $user->getEmail(),
                $band->getName(),
            ),
        );

        return Command::SUCCESS;
    }
}
