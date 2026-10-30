<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Band;
use App\Entity\BandMembership;
use App\Entity\FinanceNote;
use App\Entity\MemberProfile;
use App\Entity\MerchProduct;
use App\Entity\MerchVariant;
use App\Entity\StockMovement;
use App\Entity\TreasuryAccount;
use App\Entity\TreasuryEntry;
use App\Entity\User;
use App\Enum\BandRole;
use App\Enum\MemberKind;
use App\Enum\StockMovementKind;
use App\Enum\TreasuryAccountKind;
use App\Enum\TreasuryEntryKind;
use App\Repository\MemberProfileRepository;
use App\Service\Member\AccountActivator;
use App\Service\Member\ActivationCodeWriter;
use App\Service\Member\ProfileCipher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:band:import-private',
    description: 'Importe un jeu privé de profils, soldes et stocks, sans écraser les données déjà importées.',
),]
final class ImportPrivateBandCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MemberProfileRepository $profiles,
        private readonly ProfileCipher $cipher,
        private readonly AccountActivator $activator,
        private readonly ValidatorInterface $validator,
        private readonly ActivationCodeWriter $codeWriter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'Fichier JSON privé, à conserver hors Git.',
        )->addOption('band', null, InputOption::VALUE_REQUIRED, 'Identifiant du groupe.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = realpath((string) $input->getArgument('file'));
        if (false === $path || !is_file($path)) {
            $io->error('Fichier privé introuvable.');

            return Command::INVALID;
        }
        $data = json_decode(
            file_get_contents($path) ?:
            throw new \RuntimeException('Le fichier demandé est vide ou illisible.'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $bands = $this->em->getRepository(Band::class);
        $band =
            null !== $input->getOption('band')
                ? $bands->find((int) $input->getOption('band'))
                : (1 === $bands->count([])
                    ? $bands->findOneBy([])
                    : null);
        if (!($band instanceof Band) || ($data['bandName'] ?? '') !== $band->getName()) {
            $io->error('Le fichier et le groupe sélectionné doivent correspondre.');

            return Command::INVALID;
        }
        $codes = [];
        $created = 0;
        foreach ($data['members'] ?? [] as $row) {
            $kind = MemberKind::from($row['kind']);
            $profile = $this->profiles->findOneBy(['band' => $band, 'publicName' => $row['name']]);
            if (null !== $profile && (null !== $profile->getUser() || empty($row['email']))) {
                continue;
            }
            $user = null;
            if (!empty($row['email'])) {
                $user = isset($row['existingUserId'])
                    ? $this->em->find(User::class, (int) $row['existingUserId'])
                    : $this->em
                        ->getRepository(User::class)
                        ->findOneBy(['email' => mb_strtolower(trim($row['email']))]);
                if (
                    isset($row['existingUserId'])
                    && (!($user instanceof User)
                        || null ===
                            $this->em
                                ->getRepository(BandMembership::class)
                                ->findOneBy(['band' => $band, 'user' => $user]))
                ) {
                    throw new \RuntimeException(
                        'Le rapprochement du compte existant doit appartenir au groupe.',
                    );
                }
                if (null === $user) {
                    $user = new User($row['email'], $row['name']);
                    if (count($this->validator->validate($user)) > 0) {
                        throw new \RuntimeException('Une identité du fichier privé est invalide.');
                    }
                    $this->em->persist($user);
                }
                $membership =
                    null === $user->getId()
                        ? null
                        : $this->em
                            ->getRepository(BandMembership::class)
                            ->findOneBy(['band' => $band, 'user' => $user]);
                $role = MemberKind::Booker === $kind ? BandRole::Booker : BandRole::Manager;
                if (null === $membership) {
                    $this->em->persist(new BandMembership($band, $user, $role));
                } else {
                    $membership->setRole($role);
                }
            }
            if (null === $profile) {
                $profile = new MemberProfile($band, $user, $row['name'], $row['function'], $kind);
                $this->em->persist($profile);
                ++$created;
            } elseif (null !== $user) {
                $profile->attachUser($user);
            }
            if (!empty($row['administrative'])) {
                $profile->setAdministrativeCiphertext(
                    $this->cipher->encrypt($profile, $row['administrative']),
                );
            }
            if (null !== $user && !$user->isActivated()) {
                $codes[] = [
                    'name' => $profile->getPublicName(),
                    'code' => $this->activator->issue($profile),
                    'expires' => (
                        $profile->getActivationExpiresAt() ??
                        throw new \LogicException('Le code doit avoir une date d’expiration.')
                    )->format('d/m/Y'),
                ];
            }
        }
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));
        foreach ($data['accounts'] ?? [] as $key => $cents) {
            $kind = TreasuryAccountKind::from($key);
            if (
                null !==
                $this->em
                    ->getRepository(TreasuryAccount::class)
                    ->findOneBy(['band' => $band, 'kind' => $kind])
            ) {
                continue;
            }
            if (!is_int($cents) || $cents < 0) {
                throw new \RuntimeException(
                    'Un solde initial doit être un nombre entier positif de centimes.',
                );
            }
            $account = new TreasuryAccount($band, $kind);
            $this->em->persist($account);
            if ($cents > 0) {
                $account->credit($cents);
                $this->em->persist(
                    new TreasuryEntry(
                        $account,
                        TreasuryEntryKind::Opening,
                        $cents,
                        'Solde de départ validé',
                        'Initialisation',
                        $today,
                    ),
                );
            }
        }
        foreach ($data['products'] ?? [] as $row) {
            if (
                null !==
                $this->em
                    ->getRepository(MerchProduct::class)
                    ->findOneBy(['band' => $band, 'name' => $row['name']])
            ) {
                continue;
            }
            $product = new MerchProduct($band, $row['name'], $row['category']);
            foreach ($row['variants'] as $variantData) {
                $variant = new MerchVariant($product, $variantData['label']);
                if (null !== $variantData['quantity']) {
                    if (!is_int($variantData['quantity'])) {
                        throw new \RuntimeException('Stock entier attendu.');
                    }
                    $variant->setQuantity(
                        $variantData['quantity'],
                        $variantData['needsVerification'] ?? false,
                    );
                    $this->em->persist(
                        new StockMovement(
                            $variant,
                            StockMovementKind::Initial,
                            null,
                            $variantData['quantity'],
                            'Stock de départ fourni par le groupe',
                        ),
                    );
                }
            }
            $this->em->persist($product);
        }
        if (0 === $this->em->getRepository(FinanceNote::class)->count(['band' => $band])) {
            foreach ($data['notes'] ?? [] as $text) {
                $this->em->persist(new FinanceNote($band, $text));
            }
        }
        if ([] === $band->getUsefulLinks()) {
            $links = $data['links'] ?? [];
            foreach ($links as $link) {
                if (
                    !filter_var($link['url'], FILTER_VALIDATE_URL)
                    || 'https' !== parse_url($link['url'], PHP_URL_SCHEME)
                ) {
                    throw new \RuntimeException('Les liens du groupe doivent utiliser HTTPS.');
                }
            }
            $band->setUsefulLinks($links);
        }
        $codeFile = [] !== $codes ? $this->codeWriter->write($codes) : null;
        try {
            $this->em->flush();
        } catch (\Throwable $error) {
            if (null !== $codeFile) {
                unlink($codeFile);
            }
            throw $error;
        }
        $io->success(
            sprintf(
                '%d profils créés. Les données déjà importées et les mots de passe existants sont conservés.',
                $created,
            ),
        );
        if (null !== $codeFile) {
            $io->text('Codes personnels disponibles uniquement dans : '.$codeFile);
        }

        return Command::SUCCESS;
    }
}
