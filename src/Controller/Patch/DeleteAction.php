<?php

declare(strict_types=1);

namespace App\Controller\Patch;

use App\Entity\PatchLine;
use App\Security\Voter\TechnicalSheetVoter;
use App\Service\Patch\PatchLineArchiver;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/sources/{id}/delete',
    name: 'app_patch_delete',
    requirements: ['id' => '\d+'],
    methods: ['POST'],
),]
final class DeleteAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] PatchLine $line,
        PatchLineArchiver $archiver,
    ): Response {
        $sheet = $line->getSheet() ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted(TechnicalSheetVoter::DELETE, $sheet);
        if (
            !$this->isCsrfTokenValid(
                'patch_delete_'.$line->getId(),
                $request->getPayload()->getString('_token'),
            )
        ) {
            throw $this->createAccessDeniedException('Le formulaire a expiré.');
        }
        $archiver->archive($line);
        $this->addFlash('patch_removed', [
            'id' => $line->getId(),
            'name' => $line->getSourceName(),
        ]);

        return null !== $sheet->getConcert()
            ? $this->redirectToRoute(
                'app_concert_show',
                ['id' => $sheet->getConcert()->getId()],
                Response::HTTP_SEE_OTHER,
            )
            : $this->redirectToRoute('app_patch_index', [], Response::HTTP_SEE_OTHER);
    }
}
