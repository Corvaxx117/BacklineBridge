<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Calendar;

use App\Dto\Calendar\CalendarPeriod;
use App\Integration\GoogleCalendar\Dto\AgendaEvent;
use App\Integration\GoogleCalendar\Dto\AgendaListing;
use App\Service\Calendar\CalendarEventPresenter;
use PHPUnit\Framework\TestCase;

final class CalendarPeriodTest extends TestCase
{
    public function testCalendarWeekKeepsLocalMidnightAcrossDaylightSavingChanges(): void
    {
        $period = CalendarPeriod::fromQuery('2027-03-22', '2027-03-29', new \DateTimeZone('Europe/Paris'));
        self::assertSame('2027-03-22T00:00:00+01:00', $period->from->format(\DateTimeInterface::RFC3339));
        self::assertSame('2027-03-29T00:00:00+02:00', $period->until->format(\DateTimeInterface::RFC3339));
        self::assertSame(7 * 86400 - 3600, $period->until->getTimestamp() - $period->from->getTimestamp());
    }

    public function testTimedEventsKeepTheirOffsetsAndNeverIncludePrivateKeysOrNotes(): void
    {
        $start = new \DateTimeImmutable('2027-07-10 20:00', new \DateTimeZone('Europe/Paris'));
        $events = new CalendarEventPresenter()->events(new AgendaListing([
            new AgendaEvent('Concert', 'Salle', $start, $start->modify('+1 hour'), false, null),
        ], false, new \DateTimeImmutable('now')));
        self::assertSame('2027-07-10T20:00:00+02:00', $events[0]['start']);
        self::assertSame('2027-07-10T21:00:00+02:00', $events[0]['end']);
        self::assertSame(['location' => 'Salle'], $events[0]['extendedProps']);
    }
}
