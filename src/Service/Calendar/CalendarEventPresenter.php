<?php

declare(strict_types=1);

namespace App\Service\Calendar;

use App\Integration\GoogleCalendar\Dto\AgendaEvent;
use App\Integration\GoogleCalendar\Dto\AgendaListing;

final readonly class CalendarEventPresenter
{
    /** @return list<array{title: string, start: string, end: string, allDay: bool, url: ?string, extendedProps: array{location: string}}> */
    public function events(AgendaListing $listing): array
    {
        return array_map(static fn (AgendaEvent $event): array => [
            'title' => $event->title,
            'start' => $event->start->format($event->allDay ? 'Y-m-d' : \DateTimeInterface::RFC3339),
            // Le navigateur attend une fin exclusive pour les journées entières.
            'end' => $event->allDay
                ? $event->end->modify('+1 day')->format('Y-m-d')
                : $event->end->format(\DateTimeInterface::RFC3339),
            'allDay' => $event->allDay,
            'url' => $event->url,
            'extendedProps' => ['location' => $event->location],
        ], $listing->events);
    }
}
