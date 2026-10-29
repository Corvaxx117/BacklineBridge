<?php

declare(strict_types=1);

namespace App\Enum;

enum TreasuryAccountKind: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    public function label(): string
    {
        return $this === self::Cash ? 'Caisse physique' : 'Compte de l’asso';
    }
}
