<?php

declare(strict_types=1);

namespace App\Controller\Concert\Calendar;

use App\Entity\Concert;
use App\Entity\User;
use App\Integration\GoogleCalendar\Exception\GoogleCalendarUnavailable;
use App\Security\Voter\BandVoter;
use App\Service\Calendar\ConcertPublisher;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/concerts/{id}/agenda-google', name: 'app_concert_calendar_publish', requirements: ['id' => '\d+'], methods: ['POST'])]
final class PublishAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] Concert $concert,
        #[CurrentUser] User $user,
        ConcertPublisher $publisher,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::CALENDAR_PUBLISH, $concert->getBand());
        if (!$this->isCsrfTokenValid('calendar_publish_'.$concert->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Le formulaire a expiré. Recharge la page puis réessaie.');
        }
        try {
            $result = $publisher->publish($concert, $user);
            $this->addFlash('success', $result->created
                ? 'Le concert a été ajouté à l’agenda Google du groupe.'
                : 'Ce concert est déjà dans l’agenda Google. Aucun doublon n’a été créé ; ses informations n’ont pas été remplacées.');
            if (null !== $result->url) {
                $this->addFlash('calendar_event_url', $result->url);
            }
        } catch (GoogleCalendarUnavailable $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_concert_show', [
            'id' => $concert->getId(),
            '_fragment' => 'concert-calendar',
        ], Response::HTTP_SEE_OTHER);
    }
}
