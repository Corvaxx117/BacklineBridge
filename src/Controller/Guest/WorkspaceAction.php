<?php

declare(strict_types=1);

namespace App\Controller\Guest;

use App\Dto\Venue\VenueReplyInput;
use App\Entity\VenueReply;
use App\Form\Venue\VenueReplyType;
use App\Service\Venue\GuestAccess;
use App\Service\Venue\PublicationBuilder;
use App\Service\Venue\ReplyRecorder;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/salle', name: 'app_guest_workspace', methods: ['GET', 'POST'])]
final class WorkspaceAction extends AbstractController
{
    public function __invoke(
        Request $request,
        GuestAccess $access,
        PublicationBuilder $builder,
        ReplyRecorder $recorder,
        EntityManagerInterface $em,
    ): Response {
        $invitation = $access->invitation($request);
        $context = $access->context($request);
        $publication = $invitation->getPublication();
        $snapshot = $publication->getSnapshot();
        $requests = $builder->requests($snapshot);
        $reply = $em->getRepository(VenueReply::class)->findOneBy(['invitation' => $invitation]);
        $input = VenueReplyInput::forRequests($requests, $reply);
        $form = $this->createForm(VenueReplyType::class, $input, [
            'action' => $this->generateUrl('app_guest_workspace', ['access' => $context]),
            'csrf_token_id' => 'venue_reply_'.$invitation->getId().'_'.$publication->getId(),
        ])->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $recorder->save($invitation, $input, $reply);
                $this->addFlash(
                    'success',
                    'Votre réponse a été enregistrée. Vous pourrez la compléter avec ce même lien.',
                );

                return $this->redirectToRoute('app_guest_workspace', ['access' => $context], 303);
            } catch (ConflictHttpException|OptimisticLockException|UniqueConstraintViolationException) {
                $form->addError(
                    new FormError(
                        'La réponse a changé dans un autre onglet. Rechargez la page avant de réessayer.',
                    ),
                );
                $status = 409;
            }
        }

        return $this->render(
            'guest/workspace.html.twig',
            [
                'invitation' => $invitation,
                'access' => $context,
                'publication' => $publication,
                'snapshot' => $snapshot,
                'requests' => $requests,
                'reply' => $reply,
                'form' => $form->createView(),
            ],
            new Response(status: $status),
        );
    }
}
