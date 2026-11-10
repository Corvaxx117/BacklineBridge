<?php

declare(strict_types=1);

namespace App\Enum;

enum FeeBasis: string
{
    case Unknown = 'unknown';
    case Ht = 'ht';
    case Ttc = 'ttc';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'À préciser',
            self::Ht => 'HT',
            self::Ttc => 'TTC',
        };
    }
}
