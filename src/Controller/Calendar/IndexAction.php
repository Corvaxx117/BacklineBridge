<?php

declare(strict_types=1);

namespace App\Controller\Calendar;

use App\Dto\Calendar\CalendarPeriod;
use App\Entity\Band;
use App\Integration\GoogleCalendar\Exception\GoogleCalendarUnavailable;
use App\Integration\GoogleCalendar\GoogleCalendarConfiguration;
use App\Security\Voter\BandVoter;
use App\Service\Calendar\CalendarReader;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/groupes/{id}/agenda', name: 'app_calendar_index', requirements: ['id' => '\d+'], methods: ['GET'])]
final class IndexAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Band $band,
        GoogleCalendarConfiguration $configuration,
        CalendarReader $reader,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::CALENDAR, $band);
        $listing = null;
        $error = null;
        $configured = $configuration->isConfiguredFor($band);
        if ($configured) {
            try {
                $first = new \DateTimeImmutable('first day of this month 00:00', $configuration->timezone());
                $from = $first->modify('monday this week');
                $listing = $reader->forPeriod($band, new CalendarPeriod($from, $from->modify('+42 days')));
            } catch (GoogleCalendarUnavailable $exception) {
                $error = $exception->getMessage();
            }
        }

        return $this->render('calendar/index.html.twig', [
            'band' => $band,
            'listing' => $listing,
            'error' => $error,
            'configured' => $configured,
            'calendarName' => $configuration->name(),
            'today' => $configured ? new \DateTimeImmutable('today', $configuration->timezone())->format('Y-m-d') : null,
            'timezone' => $configured ? $configuration->timezone()->getName() : null,
        ], new Response(status: null === $error ? 200 : 503));
    }
}
