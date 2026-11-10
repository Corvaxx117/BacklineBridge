<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationType: string
{
    case ConcertCreated = 'concert_created';
    case ConcertsImported = 'concerts_imported';
    case OrfeoConcertsImported = 'orfeo_concerts_imported';
    case DocumentAdded = 'document_added';
    case RoadmapAdded = 'roadmap_added';

    public function label(): string
    {
        return match ($this) {
            self::ConcertCreated => 'Nouveau concert',
            self::ConcertsImported => 'Import Bandsintown',
            self::OrfeoConcertsImported => 'Import Orfeo',
            self::DocumentAdded => 'Nouveau document',
            self::RoadmapAdded => 'Feuille de route',
        };
    }

    public function action(int $count): string
    {
        return match ($this) {
            self::ConcertCreated => 'a créé un concert.',
            self::ConcertsImported => 'a importé '.
                (1 === $count ? 'un concert' : $count.' concerts').' depuis Bandsintown.',
            self::OrfeoConcertsImported => 'a importé '.(1 === $count ? 'un concert' : $count.' concerts').' depuis Orfeo.',
            self::DocumentAdded => 'a ajouté un document.',
            self::RoadmapAdded => 'a ajouté une feuille de route.',
        };
    }
}
