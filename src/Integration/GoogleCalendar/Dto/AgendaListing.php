<?php

declare(strict_types=1);

namespace App\Integration\GoogleCalendar\Dto;

final readonly class AgendaListing
{
    /** @param list<AgendaEvent> $events */
    public function __construct(
        public array $events,
        public bool $hasMore,
        public \DateTimeImmutable $fetchedAt,
    ) {
    }
}
