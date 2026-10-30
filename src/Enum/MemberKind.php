<?php

declare(strict_types=1);

namespace App\Enum;

enum MemberKind: string
{
    case Musician = 'musician';
    case Engineer = 'engineer';
    case Booker = 'booker';

    public function label(): string
    {
        return match ($this) {
            self::Musician => 'Membre du groupe',
            self::Engineer => 'Ingénieur son',
            self::Booker => 'Booker',
        };
    }
}
