<?php

declare(strict_types=1);

namespace App\Controller\Guest;

use App\Service\Pdf\TechnicalDossierFactory;
use App\Service\Pdf\TechnicalPdfResponseFactory;
use App\Service\Venue\GuestAccess;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/salle/fiche-technique.pdf', name: 'app_guest_pdf', methods: ['GET'])]
final class DownloadPdfAction
{
    public function __invoke(
        Request $request,
        GuestAccess $access,
        TechnicalDossierFactory $dossiers,
        TechnicalPdfResponseFactory $pdf,
    ): Response {
        $invitation = $access->invitation($request);

        return $pdf->download($dossiers->forPublication($invitation->getPublication()));
    }
}
