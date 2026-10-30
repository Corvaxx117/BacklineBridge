<?php

declare(strict_types=1);

namespace App\Controller\Concert\Import\Bandsintown;

use App\Dto\Concert\BandsintownFetchInput;
use App\Entity\Band;
use App\Entity\User;
use App\Form\Concert\BandsintownFetchType;
use App\Integration\Bandsintown\BandsintownConfiguration;
use App\Integration\Bandsintown\BandsintownGateway;
use App\Integration\Bandsintown\Dto\BandsintownEvent;
use App\Integration\Bandsintown\Exception\BandsintownUnavailable;
use App\Security\Voter\BandVoter;
use App\Service\Concert\Import\Bandsintown\PreviewStore;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(
    '/groupes/{id}/concerts/import-bandsintown',
    name: 'app_concert_bandsintown_prepare',
    requirements: ['id' => '\d+'],
    methods: ['GET', 'POST'],
),]
final class PrepareAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Band $band,
        #[CurrentUser] User $user,
        Request $request,
        BandsintownConfiguration $configuration,
        BandsintownGateway $gateway,
        PreviewStore $previews,
        ClockInterface $clock,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::PLAN_CONCERT, $band);
        $configured = $configuration->isConfiguredFor($band);
        $input = new BandsintownFetchInput();
        $form = $this->createForm(BandsintownFetchType::class, $input)->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($configured && $form->isSubmitted() && $form->isValid()) {
            try {
                $result = $gateway->events();
                $today = $clock
                    ->now()
                    ->setTimezone(new \DateTimeZone('Europe/Paris'))
                    ->format('Y-m-d');
                $events = array_values(
                    array_filter(
                        $result->events,
                        static fn (BandsintownEvent $event): bool => match ($input->period) {
                            'past' => $event->date < $today,
                            'upcoming' => $event->date >= $today,
                            default => true,
                        },
                    ),
                );
                usort(
                    $events,
                    static fn (BandsintownEvent $a, BandsintownEvent $b): int => [
                        $a->date,
                        $a->localTime,
                        $a->id,
                    ] <=> [$b->date, $b->localTime, $b->id],
                );
                $token = $previews->save($band, $user, $result, $events);

                return $this->redirectToRoute(
                    'app_concert_bandsintown_review',
                    ['id' => $band->getId(), 'token' => $token],
                    303,
                );
            } catch (BandsintownUnavailable $error) {
                $form->addError(new FormError($error->getMessage()));
                $status = 503;
            }
        }

        return $this->render(
            'concert/import/bandsintown/prepare.html.twig',
            ['band' => $band, 'configured' => $configured, 'form' => $form->createView()],
            new Response(status: $status),
        );
    }
}
