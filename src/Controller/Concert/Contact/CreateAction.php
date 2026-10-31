<?php

declare(strict_types=1);

namespace App\Controller\Concert\Contact;

use App\Dto\Concert\ContactInput;
use App\Entity\Concert;
use App\Form\Concert\ContactType;
use App\Security\Voter\ConcertVoter;
use App\Service\Concert\ContactManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/concerts/{id}/contacts/ajouter',
    name: 'app_concert_contact_create',
    requirements: ['id' => '\d+'],
    methods: ['GET', 'POST'],
)]
final class CreateAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] Concert $concert,
        ContactManager $contacts,
    ): Response {
        $this->denyAccessUnlessGranted(ConcertVoter::CONTACTS, $concert);
        $input = new ContactInput();
        $form = $this->createForm(ContactType::class, $input)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $contacts->save($concert, $input);
            $this->addFlash('success', 'Le contact a été ajouté au concert.');

            return $this->redirectToRoute(
                'app_concert_show',
                ['id' => $concert->getId(), '_fragment' => 'concert-contacts'],
                Response::HTTP_SEE_OTHER,
            );
        }

        return $this->render('concert/contact/form.html.twig', [
            'concert' => $concert,
            'form' => $form,
            'editing' => false,
        ], new Response(status: $form->isSubmitted() ? 422 : 200));
    }
}
