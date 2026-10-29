<?php

declare(strict_types=1);

namespace App\Controller\Concert\Invitation;

use App\Dto\Venue\InvitationInput;
use App\Entity\Concert;
use App\Form\Venue\InvitationType;
use App\Security\Voter\ConcertVoter;
use App\Service\Venue\VenueWorkspace;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[
    Route(
        '/concerts/{id}/invitations',
        name: 'app_concert_invitations',
        requirements: ['id' => '\d+'],
        methods: ['GET'],
    ),
]
final class IndexAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Concert $concert,
        VenueWorkspace $workspace,
    ): Response {
        $this->denyAccessUnlessGranted(ConcertVoter::SHARE, $concert);
        $form = $this->createForm(InvitationType::class, new InvitationInput(), [
            'action' => $this->generateUrl('app_concert_invitation_create', [
                'id' => $concert->getId(),
            ]),
        ]);

        return $this->render('concert/invitations.html.twig', [
            'concert' => $concert,
            'form' => $form->createView(),
            'invitations' => $workspace->invitations($concert),
        ]);
    }
}
