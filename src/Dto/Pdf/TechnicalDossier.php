<?php

declare(strict_types=1);

namespace App\Dto\Pdf;

/** Les seules données techniques autorisées dans un document partageable. */
final readonly class TechnicalDossier
{
    public function __construct(
        public string $bandName,
        public string $label,
        public ?string $date,
        public ?string $venue,
        public ?string $city,
        public ?string $notes,
        public array $notices,
        public array $lines,
        public array $stagePlan,
        public array $equipment,
        public ?\DateTimeImmutable $publishedAt = null,
        public ?string $documentLabel = null,
    ) {}
}
