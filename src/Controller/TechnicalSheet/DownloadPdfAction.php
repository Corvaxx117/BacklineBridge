<?php

declare(strict_types=1);

namespace App\Controller\TechnicalSheet;

use App\Entity\TechnicalSheet;
use App\Security\Voter\TechnicalSheetVoter;
use App\Service\Pdf\{TechnicalDossierFactory, TechnicalPdfResponseFactory};
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[
    Route(
        '/fiches/{id}/fiche-technique.pdf',
        name: 'app_sheet_pdf',
        requirements: ['id' => '\d+'],
        methods: ['GET'],
    ),
]
final class DownloadPdfAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] TechnicalSheet $sheet,
        TechnicalDossierFactory $dossiers,
        TechnicalPdfResponseFactory $pdf,
    ): Response {
        $this->denyAccessUnlessGranted(TechnicalSheetVoter::VIEW, $sheet);

        return $pdf->download($dossiers->forSheet($sheet));
    }
}
