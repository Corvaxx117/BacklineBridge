<?php

declare(strict_types=1);

namespace App\Enum;

enum TreasuryEntryKind: string
{
    case Opening = 'opening';
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Solde de départ',
            self::Income => 'Recette',
            self::Expense => 'Dépense',
            self::Transfer => 'Transfert',
        };
    }
}
