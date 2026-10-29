<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Dto\Finance\{TreasuryEntryInput, TransferInput, CancelEntryInput};
use App\Entity\{Band, User, TreasuryEntry, TreasuryAccount, StockMovement};
use App\Enum\{TreasuryEntryKind, StockMovementKind};
use App\Repository\TreasuryRepository;
use App\Service\Money\EuroAmount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class TreasuryManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private TreasuryRepository $treasury,
        private MusicianAccess $access,
        private ValidatorInterface $validator,
    ) {}

    public function fingerprint(Band $band): string
    {
        return hash(
            'sha256',
            json_encode(
                array_map(
                    static fn(TreasuryAccount $account): array => [
                        $account->getId(),
                        $account->getRevision(),
                    ],
                    $this->treasury->accounts($band),
                ),
                JSON_THROW_ON_ERROR,
            ),
        );
    }

    public function assertCurrent(Band $band, string $expected): void
    {
        if (!hash_equals($this->fingerprint($band), $expected)) {
            throw new ConflictHttpException(
                'Les soldes ont changé. Recharge le formulaire avant de réessayer.',
            );
        }
    }

    public function record(Band $band, User $actor, TreasuryEntryInput $input): TreasuryEntry
    {
        $this->access->assert($actor, $band);
        $this->validate($input);
        $this->assertCurrent($band, $input->revision);
        if (
            !in_array($input->kind, [TreasuryEntryKind::Income, TreasuryEntryKind::Expense], true)
        ) {
            throw new \DomainException('Choisis une recette ou une dépense.');
        }
        $amount = EuroAmount::toCents($input->amount) ?? 0;
        if ($amount < 1) {
            throw new \DomainException('Le montant doit être supérieur à zéro.');
        }
        $account = $this->treasury->account($band, $input->account);
        if ($input->kind === TreasuryEntryKind::Income) {
            $account->credit($amount);
        } else {
            $account->debit($amount);
        }
        $entry = new TreasuryEntry(
            $account,
            $input->kind,
            $amount,
            trim($input->label),
            $input->category,
            $input->occurredOn,
            $actor,
        );
        $this->em->persist($entry);
        $this->em->flush();
        return $entry;
    }

    public function transfer(Band $band, User $actor, TransferInput $input): void
    {
        $this->access->assert($actor, $band);
        $this->validate($input);
        $this->assertCurrent($band, $input->revision);
        if ($input->source === $input->destination) {
            throw new \DomainException('Choisis deux comptes différents.');
        }
        $amount = EuroAmount::toCents($input->amount) ?? 0;
        $source = $this->treasury->account($band, $input->source);
        $destination = $this->treasury->account($band, $input->destination);
        $source->debit($amount);
        $destination->credit($amount);
        $this->em->persist(
            new TreasuryEntry(
                $source,
                TreasuryEntryKind::Transfer,
                $amount,
                trim($input->label),
                'Transfert',
                $input->occurredOn,
                $actor,
                $destination,
            ),
        );
        $this->em->flush();
    }

    public function cancel(TreasuryEntry $entry, User $actor, CancelEntryInput $input): void
    {
        $this->access->assert($actor, $entry->getBand());
        $this->validate($input);
        $this->assertCurrent($entry->getBand(), $input->revision);
        if ($input->entryRevision !== (string) $entry->getRevision()) {
            throw new ConflictHttpException('Ce mouvement a changé. Recharge la page.');
        }
        if ($entry->isCancelled() || $entry->getKind() === TreasuryEntryKind::Opening) {
            throw new \DomainException('Ce mouvement ne peut pas être annulé.');
        }
        $amount = $entry->getAmountCents();
        // Effectuer tous les contrôles avant de modifier les objets ; flush reste atomique.
        if (
            $entry->getKind() === TreasuryEntryKind::Income &&
            $entry->getAccount()->getBalanceCents() < $amount
        ) {
            throw new \DomainException('Le solde ne permet pas d’annuler cette recette.');
        }
        if (
            $entry->getKind() === TreasuryEntryKind::Transfer &&
            $entry->getDestination()->getBalanceCents() < $amount
        ) {
            throw new \DomainException(
                'Le compte destinataire ne permet pas de restituer ce transfert.',
            );
        }
        if ($movement = $entry->getStockMovement()) {
            $variant = $movement->getVariant();
            if ($variant->getQuantity() === null) {
                throw new \DomainException('Compte le stock avant d’annuler cette vente.');
            }
            $before = $variant->getQuantity();
            $after = $before + ($movement->getBeforeQuantity() - $movement->getAfterQuantity());
            $variant->setQuantity($after, $variant->needsVerification());
            $this->em->persist(
                new StockMovement(
                    $variant,
                    StockMovementKind::Return,
                    $before,
                    $after,
                    mb_substr('Annulation : ' . trim($input->reason), 0, 250),
                    $actor,
                ),
            );
        }
        match ($entry->getKind()) {
            TreasuryEntryKind::Income => $entry->getAccount()->debit($amount),
            TreasuryEntryKind::Expense => $entry->getAccount()->credit($amount),
            TreasuryEntryKind::Transfer => $this->reverseTransfer($entry),
            default => throw new \DomainException('Annulation impossible.'),
        };
        $entry->cancel($actor, trim($input->reason));
        $this->em->flush();
    }

    private function reverseTransfer(TreasuryEntry $entry): void
    {
        $entry->getDestination()->debit($entry->getAmountCents());
        $entry->getAccount()->credit($entry->getAmountCents());
    }

    private function validate(object $input): void
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new \DomainException((string) $errors[0]->getMessage());
        }
    }
}
