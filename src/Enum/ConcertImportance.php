<?php

declare(strict_types=1);

namespace App\Enum;

enum ConcertImportance: int
{
    case Low = 1;
    case Moderate = 2;
    case Notable = 3;
    case High = 4;
    case Major = 5;

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Faible',
            self::Moderate => 'Modéré',
            self::Notable => 'Notable',
            self::High => 'Fort',
            self::Major => 'Majeur',
        };
    }
}
