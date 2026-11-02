<?php

declare(strict_types=1);

namespace App\Integration\GoogleCalendar\Dto;

final readonly class EventDraft
{
    public function __construct(
        public string $id,
        public string $title,
        public string $location,
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
    ) {
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'id' => $this->id,
            'summary' => $this->title,
            'location' => $this->location,
            'start' => [
                'dateTime' => $this->start->format(\DateTimeInterface::RFC3339),
                'timeZone' => $this->start->getTimezone()->getName(),
            ],
            'end' => [
                'dateTime' => $this->end->format(\DateTimeInterface::RFC3339),
                'timeZone' => $this->end->getTimezone()->getName(),
            ],
            'extendedProperties' => ['private' => ['backlinebridgeEventId' => $this->id]],
        ];
    }
}
