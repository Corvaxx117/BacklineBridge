<?php

declare(strict_types=1);

namespace App\Dto\Merch;

use App\Enum\StockMovementKind;
use App\Enum\TreasuryAccountKind;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class StockMovementInput
{
    #[Assert\NotNull]
    public ?StockMovementKind $kind = StockMovementKind::Sale;
    #[Assert\NotNull, Assert\Range(min: 0, max: 1000000)]
    public ?int $quantity = 1;
    #[Assert\NotBlank, Assert\Length(max: 250)]
    public string $reason = '';
    #[Assert\Regex(
        pattern: '/^\d{1,7}(?:[,.]\d{1,2})?$/D',
        message: 'Indique un montant en euros avec deux décimales maximum.',
    ),]
    public ?string $amount = null;
    public ?TreasuryAccountKind $account = TreasuryAccountKind::Cash;
    #[Assert\NotBlank]
    public string $revision = '';
    public string $treasuryRevision = '';

    #[Assert\Callback]
    public function validateSale(ExecutionContextInterface $context): void
    {
        if (
            StockMovementKind::Sale === $this->kind
            && (null === $this->amount || '' === $this->amount)
        ) {
            $context
                ->buildViolation('Indique le montant total encaissé.')
                ->atPath('amount')
                ->addViolation();
        }
        if (StockMovementKind::Sale === $this->kind && null === $this->account) {
            $context
                ->buildViolation('Choisis le compte qui reçoit l’argent.')
                ->atPath('account')
                ->addViolation();
        }
    }
}
