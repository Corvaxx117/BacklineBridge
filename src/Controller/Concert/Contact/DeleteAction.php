<?php

declare(strict_types=1);

namespace App\Controller\Concert\Contact;

use App\Entity\ConcertContact;
use App\Security\Voter\ConcertVoter;
use App\Service\Concert\ContactManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/contacts-concert/{id}/supprimer',
    name: 'app_concert_contact_delete',
    requirements: ['id' => '\d+'],
    methods: ['POST'],
)]
final class DeleteAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] ConcertContact $contact,
        ContactManager $contacts,
    ): Response {
        $concert = $contact->getConcert();
        $this->denyAccessUnlessGranted(ConcertVoter::CONTACTS, $concert);
        if (!$this->isCsrfTokenValid(
            'contact_delete_'.$contact->getId(),
            $request->getPayload()->getString('_token'),
        )) {
            throw $this->createAccessDeniedException('Le formulaire a expiré.');
        }
        $contacts->remove($contact);
        $this->addFlash('success', 'Le contact a été retiré du concert.');

        return $this->redirectToRoute(
            'app_concert_show',
            ['id' => $concert->getId(), '_fragment' => 'concert-contacts'],
            Response::HTTP_SEE_OTHER,
        );
    }
}
