<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\{
    Band,
    BandMembership,
    User,
    MemberProfile,
    TreasuryAccount,
    TreasuryEntry,
    MerchProduct,
    MerchVariant,
    StockMovement,
    FinanceNote,
};
use App\Enum\{MemberKind, BandRole, TreasuryAccountKind, TreasuryEntryKind, StockMovementKind};
use App\Repository\MemberProfileRepository;
use App\Service\Member\{ProfileCipher, AccountActivator, ActivationCodeWriter};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputArgument, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[
    AsCommand(
        name: 'app:band:import-private',
        description: 'Importe un jeu privé de profils, soldes et stocks, sans écraser les données déjà importées.',
    ),
]
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
        if ($path === false || !is_file($path)) {
            $io->error('Fichier privé introuvable.');
            return Command::INVALID;
        }
        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $bands = $this->em->getRepository(Band::class);
        $band =
            $input->getOption('band') !== null
                ? $bands->find((int) $input->getOption('band'))
                : ($bands->count([]) === 1
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
            if ($profile !== null && ($profile->getUser() !== null || empty($row['email']))) {
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
                    isset($row['existingUserId']) &&
                    (!($user instanceof User) ||
                        $this->em
                            ->getRepository(BandMembership::class)
                            ->findOneBy(['band' => $band, 'user' => $user]) === null)
                ) {
                    throw new \RuntimeException(
                        'Le rapprochement du compte existant doit appartenir au groupe.',
                    );
                }
                if ($user === null) {
                    $user = new User($row['email'], $row['name']);
                    if (count($this->validator->validate($user)) > 0) {
                        throw new \RuntimeException('Une identité du fichier privé est invalide.');
                    }
                    $this->em->persist($user);
                }
                $membership =
                    $user->getId() === null
                        ? null
                        : $this->em
                            ->getRepository(BandMembership::class)
                            ->findOneBy(['band' => $band, 'user' => $user]);
                $role = $kind === MemberKind::Booker ? BandRole::Booker : BandRole::Manager;
                if ($membership === null) {
                    $this->em->persist(new BandMembership($band, $user, $role));
                } else {
                    $membership->setRole($role);
                }
            }
            if ($profile === null) {
                $profile = new MemberProfile($band, $user, $row['name'], $row['function'], $kind);
                $this->em->persist($profile);
                ++$created;
            } elseif ($user !== null) {
                $profile->attachUser($user);
            }
            if (!empty($row['administrative'])) {
                $profile->setAdministrativeCiphertext(
                    $this->cipher->encrypt($profile, $row['administrative']),
                );
            }
            if ($user !== null && !$user->isActivated()) {
                $codes[] = [
                    'name' => $profile->getPublicName(),
                    'code' => $this->activator->issue($profile),
                    'expires' => $profile->getActivationExpiresAt()->format('d/m/Y'),
                ];
            }
        }
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));
        foreach ($data['accounts'] ?? [] as $key => $cents) {
            $kind = TreasuryAccountKind::from($key);
            if (
                $this->em
                    ->getRepository(TreasuryAccount::class)
                    ->findOneBy(['band' => $band, 'kind' => $kind]) !== null
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
                $this->em
                    ->getRepository(MerchProduct::class)
                    ->findOneBy(['band' => $band, 'name' => $row['name']]) !== null
            ) {
                continue;
            }
            $product = new MerchProduct($band, $row['name'], $row['category']);
            foreach ($row['variants'] as $variantData) {
                $variant = new MerchVariant($product, $variantData['label']);
                if ($variantData['quantity'] !== null) {
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
        if ($this->em->getRepository(FinanceNote::class)->count(['band' => $band]) === 0) {
            foreach ($data['notes'] ?? [] as $text) {
                $this->em->persist(new FinanceNote($band, $text));
            }
        }
        if ($band->getUsefulLinks() === []) {
            $links = $data['links'] ?? [];
            foreach ($links as $link) {
                if (
                    !filter_var($link['url'], FILTER_VALIDATE_URL) ||
                    parse_url($link['url'], PHP_URL_SCHEME) !== 'https'
                ) {
                    throw new \RuntimeException('Les liens du groupe doivent utiliser HTTPS.');
                }
            }
            $band->setUsefulLinks($links);
        }
        $codeFile = $codes !== [] ? $this->codeWriter->write($codes) : null;
        try {
            $this->em->flush();
        } catch (\Throwable $error) {
            if ($codeFile !== null) {
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
        if ($codeFile !== null) {
            $io->text('Codes personnels disponibles uniquement dans : ' . $codeFile);
        }
        return Command::SUCCESS;
    }
}
