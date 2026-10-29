<?php

declare(strict_types=1);

namespace App\Enum;

enum TransportCoverage: string
{
    case Unknown = 'unknown';
    case Covered = 'covered';
    case Partial = 'partial';
    case NotCovered = 'not_covered';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'À confirmer',
            self::Covered => 'Pris en charge',
            self::Partial => 'Prise en charge partielle',
            self::NotCovered => 'À notre charge',
        };
    }
}
