<?php

declare(strict_types=1);

namespace App\Enum;

enum TreasuryAccountKind: string
{
    case Cash = 'cash';
    case Bank = 'bank';

    public function label(): string
    {
        return self::Cash === $this ? 'Caisse physique' : 'Compte de l’asso';
    }
}
