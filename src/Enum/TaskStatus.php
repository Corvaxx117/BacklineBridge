<?php

declare(strict_types=1);

namespace App\Enum;

enum TaskStatus: string
{
    case Todo = 'todo';
    case Doing = 'doing';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'À faire',
            self::Doing => 'En cours',
            self::Done => 'Terminé',
        };
    }
}
