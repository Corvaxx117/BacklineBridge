<?php

declare(strict_types=1);

namespace App\Controller\Concert\Import\Orfeo;

use App\Dto\Concert\OrfeoFetchInput;
use App\Entity\Band;
use App\Entity\User;
use App\Form\Concert\OrfeoFetchType;
use App\Integration\Orfeo\Dto\EventsResult;
use App\Integration\Orfeo\Dto\OrfeoEvent;
use App\Integration\Orfeo\Exception\OrfeoUnavailable;
use App\Integration\Orfeo\OrfeoConfiguration;
use App\Integration\Orfeo\OrfeoGateway;
use App\Security\Voter\BandVoter;
use App\Service\Concert\Import\Orfeo\PreviewStore;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/groupes/{id}/concerts/import-orfeo', name: 'app_concert_orfeo_prepare', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
final class PrepareAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Band $band,
        #[CurrentUser] User $user,
        Request $request,
        OrfeoConfiguration $configuration,
        OrfeoGateway $gateway,
        PreviewStore $previews,
        ClockInterface $clock,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::PLAN_CONCERT, $band);
        $configured = $configuration->isConfiguredFor($band);
        $input = new OrfeoFetchInput();
        $eventId = $request->query->get('event');
        if (is_string($eventId) && 1 === preg_match('/^[1-9][0-9]*:[1-9][0-9]*$/D', $eventId)) {
            $input->eventId = $eventId;
            $input->period = 'all';
        }
        $form = $this->createForm(OrfeoFetchType::class, $input)->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($configured && $form->isSubmitted() && $form->isValid()) {
            try {
                $result = $gateway->events();
                $today = $clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d');
                $events = array_values(array_filter($result->events,
                    static fn (OrfeoEvent $event): bool => null !== $input->eventId
                        ? $event->id === $input->eventId
                        : match ($input->period) {
                            'upcoming' => $event->date >= $today,
                            'past' => $event->date < $today,
                            default => true,
                        }));
                usort($events, static fn (OrfeoEvent $a, OrfeoEvent $b): int => [$a->date, $a->localTime, $a->id] <=> [$b->date, $b->localTime, $b->id]);
                $token = $previews->save($band, $user, new EventsResult($events, $result->ignored, $result->fetchedAt));

                return $this->redirectToRoute('app_concert_orfeo_review', ['id' => $band->getId(), 'token' => $token], 303);
            } catch (OrfeoUnavailable $error) {
                $form->addError(new FormError($error->getMessage()));
                $status = 503;
            }
        }

        return $this->render('concert/import/orfeo/prepare.html.twig', ['band' => $band,
            'configured' => $configured, 'form' => $form->createView()], new Response(status: $status));
    }
}
