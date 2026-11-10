<?php

declare(strict_types=1);

namespace App\Integration\Orfeo\Dto;

final readonly class EventsResult
{
    /** @param list<OrfeoEvent> $events */
    public function __construct(
        public array $events,
        public int $ignored,
        public \DateTimeImmutable $fetchedAt,
    ) {
    }
}
