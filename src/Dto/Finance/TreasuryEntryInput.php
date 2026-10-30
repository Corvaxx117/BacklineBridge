<?php

declare(strict_types=1);

namespace App\Dto\Finance;

use App\Enum\TreasuryAccountKind;
use App\Enum\TreasuryEntryKind;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class TreasuryEntryInput
{
    public const CATEGORIES = [
        'Merch',
        'Concert',
        'Transport',
        'Hébergement',
        'Production',
        'Matériel',
        'Remboursement',
        'Autre',
    ];
    #[Assert\NotBlank, Assert\Length(max: 160)]
    public string $label = '';
    #[Assert\NotNull]
    public ?TreasuryEntryKind $kind = TreasuryEntryKind::Income;
    #[Assert\NotNull]
    public ?TreasuryAccountKind $account = TreasuryAccountKind::Cash;
    #[Assert\NotBlank,
        Assert\Regex(
            pattern: '/^\d{1,7}(?:[,.]\d{1,2})?$/D',
            message: 'Indique un montant en euros avec deux décimales maximum.',
        ),]
    public string $amount = '';
    #[Assert\Choice(choices: self::CATEGORIES)]
    public string $category = 'Autre';
    #[Assert\NotNull]
    public ?\DateTimeImmutable $occurredOn;
    #[Assert\NotBlank]
    public string $revision = '';

    public function __construct()
    {
        $this->occurredOn = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));
    }

    #[Assert\Callback]
    public function checkDate(ExecutionContextInterface $context): void
    {
        if (
            null !== $this->occurredOn
            && $this->occurredOn->format('Y-m-d') >
                new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'))->format('Y-m-d')
        ) {
            $context
                ->buildViolation('Un mouvement doit correspondre à une opération déjà effectuée.')
                ->atPath('occurredOn')
                ->addViolation();
        }
    }
}
