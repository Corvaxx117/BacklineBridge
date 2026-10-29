<?php

declare(strict_types=1);

namespace App\Enum;

enum AvailabilityStatus: string
{
    case Pending = 'pending';
    case Available = 'available';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'À confirmer',
            self::Available => 'Disponible',
            self::Unavailable => 'Indisponible',
        };
    }
}
