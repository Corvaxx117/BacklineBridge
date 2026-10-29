<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentMethod: string
{
    case Unknown = 'unknown';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'À confirmer',
            self::BankTransfer => 'Virement',
            self::Cheque => 'Chèque',
            self::Cash => 'Espèces',
        };
    }
}
