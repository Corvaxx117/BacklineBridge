<?php

declare(strict_types=1);

namespace App\Controller\Patch;

use App\Entity\TechnicalSheet;
use App\Repository\PatchLineRepository;
use App\Security\Voter\TechnicalSheetVoter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[
    Route(
        '/patchs/{id}/corbeille',
        name: 'app_patch_trash',
        requirements: ['id' => '\d+'],
        methods: ['GET'],
    ),
]
final class TrashAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] TechnicalSheet $sheet,
        PatchLineRepository $lines,
    ): Response {
        $this->denyAccessUnlessGranted(TechnicalSheetVoter::DELETE, $sheet);

        return $this->render('patch/trash.html.twig', [
            'sheet' => $sheet,
            'lines' => $lines->findArchivedForSheet($sheet),
        ]);
    }
}
