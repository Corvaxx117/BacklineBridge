<?php

declare(strict_types=1);

namespace App\Controller\Calendar;

use App\Dto\Calendar\CalendarPeriod;
use App\Entity\Band;
use App\Integration\GoogleCalendar\Exception\GoogleCalendarUnavailable;
use App\Integration\GoogleCalendar\GoogleCalendarConfiguration;
use App\Security\Voter\BandVoter;
use App\Service\Calendar\CalendarEventPresenter;
use App\Service\Calendar\CalendarReader;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/groupes/{id}/agenda/evenements', name: 'app_calendar_events', requirements: ['id' => '\d+'], methods: ['GET'])]
final class EventsAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Band $band,
        Request $request,
        GoogleCalendarConfiguration $configuration,
        CalendarReader $reader,
        CalendarEventPresenter $presenter,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(BandVoter::CALENDAR, $band);
        if (!$configuration->isConfiguredFor($band)) {
            return $this->json(['error' => 'L’agenda du groupe n’est pas encore connecté.'], 503);
        }
        try {
            $period = CalendarPeriod::fromQuery(
                $request->query->getString('start'),
                $request->query->getString('end'),
                $configuration->timezone(),
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        }
        try {
            $listing = $reader->forPeriod($band, $period);

            return $this->json([
                'events' => $presenter->events($listing),
                'hasMore' => $listing->hasMore,
                'fetchedAt' => $listing->fetchedAt->format(\DateTimeInterface::RFC3339),
            ]);
        } catch (GoogleCalendarUnavailable $exception) {
            return $this->json(['error' => $exception->getMessage()], 503);
        }
    }
}
