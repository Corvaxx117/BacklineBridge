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
    '/sources/{id}/restore',
    name: 'app_patch_restore',
    requirements: ['id' => '\d+'],
    methods: ['POST'],
),]
final class RestoreAction extends AbstractController
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
                'patch_restore_'.$line->getId(),
                $request->getPayload()->getString('_token'),
            )
        ) {
            throw $this->createAccessDeniedException('Le formulaire a expiré.');
        }
        $sheetId = $sheet->getId();
        $concertId = $sheet->getConcert()?->getId();
        if (!$archiver->restore($line)) {
            $this->addFlash(
                'error',
                'Ce canal est déjà utilisé. Libérez-le avant de restaurer cette source.',
            );

            return $this->redirectToRoute(
                'app_patch_trash',
                ['id' => $sheetId],
                Response::HTTP_SEE_OTHER,
            );
        }
        $this->addFlash('success', 'La source a été restaurée dans le patch.');

        return null !== $concertId
            ? $this->redirectToRoute(
                'app_concert_show',
                ['id' => $concertId],
                Response::HTTP_SEE_OTHER,
            )
            : $this->redirectToRoute('app_patch_index', [], Response::HTTP_SEE_OTHER);
    }
}
