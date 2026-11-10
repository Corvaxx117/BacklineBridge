<?php

declare(strict_types=1);

namespace App\Dto\Concert;

use App\Enum\ConcertImportance;
use App\Enum\ConcertStatus;

/** Données des cartes, sans charger le patch, le matériel ou le plan. */
final readonly class ConcertCard
{
    public function __construct(
        public int $id,
        public \DateTimeImmutable $date,
        public string $venue,
        public string $city,
        public ConcertStatus $status,
        public string $bandName,
        public ?string $createdByName,
        public ?ConcertImportance $importance = null,
        public ?string $importanceNote = null,
        public int $revision = 1,
        public bool $canManageImportance = false,
    ) {
    }
}
