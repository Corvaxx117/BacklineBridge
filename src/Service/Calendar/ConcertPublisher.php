<?php

declare(strict_types=1);

namespace App\Service\Calendar;

use App\Entity\Concert;
use App\Entity\User;
use App\Enum\ConcertStatus;
use App\Integration\GoogleCalendar\Dto\EventDraft;
use App\Integration\GoogleCalendar\Dto\PublicationResult;
use App\Integration\GoogleCalendar\Exception\GoogleCalendarUnavailable;
use App\Integration\GoogleCalendar\GoogleCalendarConfiguration;
use App\Integration\GoogleCalendar\GoogleCalendarGateway;
use App\Service\Finance\MusicianAccess;

final readonly class ConcertPublisher
{
    public function __construct(
        private GoogleCalendarGateway $google,
        private GoogleCalendarConfiguration $configuration,
        private MusicianAccess $musicians,
        private CalendarReader $reader,
    ) {
    }

    public function publish(Concert $concert, User $user): PublicationResult
    {
        $this->musicians->assert($user, $concert->getBand());
        if (null === $concert->getId() || !$this->configuration->isConfiguredFor($concert->getBand())) {
            throw new GoogleCalendarUnavailable('L’agenda du groupe n’est pas encore connecté.');
        }
        $start = new \DateTimeImmutable(
            $concert->getDate()->format('Y-m-d').' '.($concert->getStartTime() ?? '20:00'),
            $this->configuration->timezone(),
        );
        $title = $concert->getBand()->getName().' — '.$concert->getVenue();
        if (ConcertStatus::Pending === $concert->getStatus()) {
            $title = '[En attente] '.$title;
        }
        $result = $this->google->publish(new EventDraft(
            $this->configuration->eventId($concert->getId()),
            $title,
            $concert->getVenue().', '.$concert->getCity(),
            $start,
            $start->add(new \DateInterval('PT1H')),
        ));
        $this->reader->invalidate();

        return $result;
    }
}
