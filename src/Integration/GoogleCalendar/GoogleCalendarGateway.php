<?php

declare(strict_types=1);

namespace App\Integration\GoogleCalendar;

use App\Integration\GoogleCalendar\Dto\AgendaListing;
use App\Integration\GoogleCalendar\Dto\EventDraft;
use App\Integration\GoogleCalendar\Dto\PublicationResult;

interface GoogleCalendarGateway
{
    public function events(\DateTimeImmutable $from, \DateTimeImmutable $until): AgendaListing;

    public function publish(EventDraft $event): PublicationResult;
}
