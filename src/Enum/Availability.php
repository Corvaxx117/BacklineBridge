<?php

declare(strict_types=1);

namespace App\Enum;

enum Availability: string
{
    case Unknown = 'unknown';
    case Available = 'available';
    case Alternative = 'alternative';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'À confirmer',
            self::Available => 'Disponible',
            self::Alternative => 'Alternative proposée',
            self::Unavailable => 'Indisponible',
        };
    }
}
