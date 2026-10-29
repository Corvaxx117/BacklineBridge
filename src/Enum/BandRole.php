<?php

declare(strict_types=1);

namespace App\Enum;

enum BandRole: string
{
    case Manager = 'manager';
    case Editor = 'editor';
    case Viewer = 'viewer';
    case Booker = 'booker';

    public function canEdit(): bool
    {
        return in_array($this, [self::Manager, self::Editor], true);
    }

    public function canPlanConcerts(): bool
    {
        return in_array($this, [self::Manager, self::Booker], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Responsable',
            self::Editor => 'Régie technique',
            self::Viewer => 'Lecture seule',
            self::Booker => 'Tourneur',
        };
    }
}
