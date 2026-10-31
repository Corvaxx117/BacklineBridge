<?php

declare(strict_types=1);

namespace App\Controller\Concert;

use App\Entity\Concert;
use App\Security\Voter\ConcertVoter;
use App\Service\Concert\ConcertRemover;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/concerts/{id}/supprimer',
    name: 'app_concert_delete',
    requirements: ['id' => '\d+'],
    methods: ['GET', 'POST'],
)]
final class DeleteAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] Concert $concert,
        ConcertRemover $remover,
    ): Response {
        $this->denyAccessUnlessGranted(ConcertVoter::DELETE, $concert);
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'concert_delete_'.$concert->getId(),
                $request->getPayload()->getString('_token'),
            )) {
                throw $this->createAccessDeniedException('Le formulaire a expiré.');
            }
            $remover->remove($concert, $request->getPayload()->getString('revision'));
            $this->addFlash('success', 'Le concert a été supprimé. Ses documents restent disponibles dans Documents.');

            return $this->redirectToRoute('app_concert_index', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('concert/delete.html.twig', ['concert' => $concert]);
    }
}
