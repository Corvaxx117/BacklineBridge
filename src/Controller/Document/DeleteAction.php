<?php

declare(strict_types=1);

namespace App\Controller\Document;

use App\Entity\BandDocument;
use App\Security\Voter\DocumentVoter;
use App\Service\Document\DocumentManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/documents/{id}/supprimer',
    name: 'app_document_delete',
    requirements: ['id' => '\d+'],
    methods: ['GET', 'POST'],
)]
final class DeleteAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] BandDocument $document,
        DocumentManager $documents,
    ): Response {
        $this->denyAccessUnlessGranted(DocumentVoter::DELETE, $document);
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'document_delete_'.$document->getId(),
                $request->getPayload()->getString('_token'),
            )) {
                throw $this->createAccessDeniedException('Le formulaire a expiré.');
            }
            $bandId = $document->getBand()->getId();
            $documents->remove($document);
            $this->addFlash('success', 'Le document a été retiré de BacklineBridge.');

            return $this->redirectToRoute('app_document_index', ['id' => $bandId], Response::HTTP_SEE_OTHER);
        }

        return $this->render('document/delete.html.twig', ['band' => $document->getBand(), 'document' => $document]);
    }
}
