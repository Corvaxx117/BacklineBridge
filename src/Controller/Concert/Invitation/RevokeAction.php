<?php

declare(strict_types=1);

namespace App\Controller\Concert\Invitation;

use App\Entity\VenueInvitation;
use App\Security\Voter\ConcertVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/invitations/{id}/revoquer',
    name: 'app_invitation_revoke',
    requirements: ['id' => '\d+'],
    methods: ['POST'],
),]
final class RevokeAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] VenueInvitation $invitation,
        EntityManagerInterface $em,
    ): Response {
        $concert = $invitation->getPublication()->getConcert();
        $this->denyAccessUnlessGranted(ConcertVoter::SHARE, $concert);
        if (
            !$this->isCsrfTokenValid(
                'invitation_revoke_'.$invitation->getId(),
                $request->request->getString('_token'),
            )
        ) {
            throw $this->createAccessDeniedException('Le formulaire a expiré.');
        }
        $invitation->revoke();
        $em->flush();
        $this->addFlash(
            'success',
            'Le lien a été révoqué. La salle ne peut plus utiliser cet accès.',
        );

        return $this->redirectToRoute('app_concert_invitations', ['id' => $concert->getId()], 303);
    }
}
