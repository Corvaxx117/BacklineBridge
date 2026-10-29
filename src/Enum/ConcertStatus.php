<?php

declare(strict_types=1);

namespace App\Enum;

enum ConcertStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Validated => 'Validé',
        };
    }
}
