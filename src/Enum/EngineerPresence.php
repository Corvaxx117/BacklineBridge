<?php

declare(strict_types=1);

namespace App\Enum;

enum EngineerPresence: string
{
    case Unknown = 'unknown';
    case Present = 'present';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'À confirmer',
            self::Present => 'Présent',
            self::Absent => 'Absent',
        };
    }
}
