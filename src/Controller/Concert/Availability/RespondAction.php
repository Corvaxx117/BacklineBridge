<?php

declare(strict_types=1);

namespace App\Controller\Concert\Availability;

use App\Entity\{Concert, User};
use App\Form\ConcertAvailabilityType;
use App\Security\Voter\ConcertVoter;
use App\Service\Concert\ConcertAvailabilityManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[
    Route(
        '/concerts/{id}/disponibilite',
        name: 'app_concert_availability',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    ),
]
final class RespondAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] Concert $concert,
        #[CurrentUser] User $user,
        ConcertAvailabilityManager $availability,
    ): Response {
        $this->denyAccessUnlessGranted(ConcertVoter::RESPOND_AVAILABILITY, $concert);
        $input = $availability->inputFor($concert, $user);
        $form = $this->createForm(ConcertAvailabilityType::class, $input);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $availability->respond($concert, $user, $input);
                $this->addFlash('success', 'Ta disponibilité a été enregistrée pour cette date.');

                return $this->redirectToRoute(
                    'app_concert_show',
                    ['id' => $concert->getId(), '_fragment' => 'disponibilites'],
                    Response::HTTP_SEE_OTHER,
                );
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render(
            'concert/availability.html.twig',
            [
                'concert' => $concert,
                'availabilityForm' => $form->createView(),
            ],
            new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY),
        );
    }
}
