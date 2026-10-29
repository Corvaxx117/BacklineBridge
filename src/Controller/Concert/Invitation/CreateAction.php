<?php

declare(strict_types=1);

namespace App\Controller\Concert\Invitation;

use App\Dto\Venue\InvitationInput;
use App\Entity\Concert;
use App\Form\Venue\InvitationType;
use App\Security\Voter\ConcertVoter;
use App\Service\Venue\{InvitationIssuer, VenueWorkspace};
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

#[
    Route(
        '/concerts/{id}/invitations/creer',
        name: 'app_concert_invitation_create',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    ),
]
final class CreateAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] Concert $concert,
        InvitationIssuer $issuer,
        VenueWorkspace $workspace,
    ): Response {
        $this->denyAccessUnlessGranted(ConcertVoter::SHARE, $concert);
        $input = new InvitationInput();
        $form = $this->createForm(InvitationType::class, $input)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $issued = $issuer->issue($concert, $input);
            $this->addFlash(
                'invitation_link',
                $this->generateUrl('app_guest_enter', ['token' => $issued->token]),
            );
            $this->addFlash(
                'success',
                'Le dossier a été publié. Copiez le lien pour le transmettre à la salle.',
            );

            return $this->redirectToRoute(
                'app_concert_invitations',
                ['id' => $concert->getId()],
                303,
            );
        }

        return $this->render(
            'concert/invitations.html.twig',
            [
                'concert' => $concert,
                'form' => $form->createView(),
                'invitations' => $workspace->invitations($concert),
            ],
            new Response(status: 422),
        );
    }
}
