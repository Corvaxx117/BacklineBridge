<?php

declare(strict_types=1);

namespace App\Integration\Bandsintown\Dto;

/** Résultat expurgé des identifiants d'accès et de toute réponse brute. */
final readonly class EventsResult
{
    /** @param list<BandsintownEvent> $events */
    public function __construct(
        public array $events,
        public int $ignored,
        public \DateTimeImmutable $fetchedAt,
    ) {}
}
