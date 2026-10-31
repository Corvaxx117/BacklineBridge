<?php

declare(strict_types=1);

namespace App\Controller\Concert\Contact;

use App\Dto\Concert\ContactInput;
use App\Entity\ConcertContact;
use App\Form\Concert\ContactType;
use App\Security\Voter\ConcertVoter;
use App\Service\Concert\ContactManager;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/contacts-concert/{id}/modifier',
    name: 'app_concert_contact_edit',
    requirements: ['id' => '\d+'],
    methods: ['GET', 'POST'],
)]
final class EditAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] ConcertContact $contact,
        ContactManager $contacts,
    ): Response {
        $concert = $contact->getConcert();
        $this->denyAccessUnlessGranted(ConcertVoter::CONTACTS, $concert);
        $input = ContactInput::fromContact($contact);
        $form = $this->createForm(ContactType::class, $input)->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $contacts->save($concert, $input, $contact);
                $this->addFlash('success', 'Le contact a été mis à jour.');

                return $this->redirectToRoute(
                    'app_concert_show',
                    ['id' => $concert->getId(), '_fragment' => 'concert-contacts'],
                    Response::HTTP_SEE_OTHER,
                );
            } catch (ConflictHttpException|OptimisticLockException) {
                $form->addError(new FormError(
                    'Ce contact a changé. Tes valeurs restent affichées : conserve-les puis recharge la page.',
                ));
                $status = 409;
            }
        }

        return $this->render('concert/contact/form.html.twig', [
            'concert' => $concert,
            'form' => $form,
            'editing' => true,
        ], new Response(status: $status));
    }
}
