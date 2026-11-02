<?php

declare(strict_types=1);

namespace App\Dto\Concert;

use App\Enum\AvailabilityStatus;
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
        public bool $canRespond,
        public ?AvailabilityStatus $myStatus,
    ) {
    }
}
