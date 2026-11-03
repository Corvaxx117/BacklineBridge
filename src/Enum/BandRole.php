<?php

declare(strict_types=1);

namespace App\Enum;

enum BandRole: string
{
    case Manager = 'manager';
    case TeamManager = 'team_manager';
    case Editor = 'editor';
    case Viewer = 'viewer';
    case Booker = 'booker';

    public function canManage(): bool
    {
        return in_array($this, [self::Manager, self::TeamManager], true);
    }

    public function canEdit(): bool
    {
        return $this->canManage() || self::Editor === $this;
    }

    public function canPlanConcerts(): bool
    {
        return $this->canManage() || self::Booker === $this;
    }

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Responsable',
            self::TeamManager => 'Manager',
            self::Editor => 'Régie technique',
            self::Viewer => 'Lecture seule',
            self::Booker => 'Booker',
        };
    }
}
