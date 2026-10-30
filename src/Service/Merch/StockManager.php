<?php

declare(strict_types=1);

namespace App\Service\Merch;

use App\Dto\Merch\StockMovementInput;
use App\Entity\MerchVariant;
use App\Entity\StockMovement;
use App\Entity\TreasuryEntry;
use App\Entity\User;
use App\Enum\StockMovementKind;
use App\Enum\TreasuryEntryKind;
use App\Repository\TreasuryRepository;
use App\Service\Finance\MusicianAccess;
use App\Service\Finance\TreasuryManager;
use App\Service\Money\EuroAmount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class StockManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private MusicianAccess $access,
        private TreasuryRepository $treasury,
        private TreasuryManager $manager,
        private ValidatorInterface $validator,
    ) {
    }

    public function record(MerchVariant $variant, User $actor, StockMovementInput $input): void
    {
        $this->access->assert($actor, $variant->getBand());
        $this->validate($input);
        assert(null !== $input->quantity);
        if ($input->revision !== (string) $variant->getRevision()) {
            throw new ConflictHttpException(
                'Le stock a changé. Recharge le formulaire avant de réessayer.',
            );
        }
        if (
            !in_array(
                $input->kind,
                [
                    StockMovementKind::Sale,
                    StockMovementKind::Count,
                    StockMovementKind::Restock,
                    StockMovementKind::Gift,
                ],
                true,
            )
        ) {
            throw new \DomainException('Choisis un mouvement de stock valide.');
        }
        $before = $variant->getQuantity();
        if (StockMovementKind::Count !== $input->kind && null === $before) {
            throw new \DomainException('Renseigne d’abord le stock avec un inventaire.');
        }
        if ($input->quantity < (StockMovementKind::Count === $input->kind ? 0 : 1)) {
            throw new \DomainException('La quantité n’est pas valide.');
        }
        $after = match ($input->kind) {
            StockMovementKind::Count => $input->quantity,
            StockMovementKind::Restock => $before + $input->quantity,
            default => $before - $input->quantity,
        };
        if ($after < 0 || $after > 1000000) {
            throw new \DomainException(
                'La quantité dépasse le stock disponible ou la limite autorisée.',
            );
        }
        $account = null;
        $amount = 0;
        if (StockMovementKind::Sale === $input->kind) {
            $this->manager->assertCurrent($variant->getBand(), $input->treasuryRevision);
            $amount = EuroAmount::toCents($input->amount) ?? 0;
            if ($amount < 1) {
                throw new \DomainException('Indique le montant total réellement encaissé.');
            }
            $account = $this->treasury->account(
                $variant->getBand(),
                $input->account ?? throw new \DomainException('Choisis le compte d’encaissement.'),
            );
        }
        $variant->setQuantity(
            $after,
            StockMovementKind::Count === $input->kind ? false : $variant->needsVerification(),
        );
        $movement = new StockMovement(
            $variant,
            $input->kind,
            $before,
            $after,
            trim($input->reason),
            $actor,
        );
        $this->em->persist($movement);
        if (null !== $account) {
            $account->credit($amount);
            $this->em->persist(
                new TreasuryEntry(
                    $account,
                    TreasuryEntryKind::Income,
                    $amount,
                    mb_substr(
                        $variant->getProduct()->getName().
                            ' · '.
                            $input->quantity.
                            ' × '.
                            $variant->getLabel(),
                        0,
                        160,
                    ),
                    'Merch',
                    new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris')),
                    $actor,
                    stockMovement: $movement,
                ),
            );
        }
        // L’écriture du stock et celle de la recette partagent la transaction du même flush.
        $this->em->flush();
    }

    private function validate(object $input): void
    {
        $errors = $this->validator->validate($input);
        foreach ($errors as $error) {
            throw new \DomainException((string) $error->getMessage());
        }
    }
}
