<?php

declare(strict_types=1);

namespace App\Service\Pdf;

use App\Dto\Pdf\TechnicalDossier;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\String\Slugger\AsciiSlugger;

final readonly class TechnicalPdfResponseFactory
{
    public function __construct(private TechnicalPdfRenderer $renderer)
    {
    }

    public function download(TechnicalDossier $dossier): Response
    {
        $slugger = new AsciiSlugger();
        $band = substr(strtolower((string) $slugger->slug($dossier->bandName)), 0, 70) ?: 'groupe';
        $scope =
            null !== $dossier->date
                ? $dossier->date.
                    '-'.
                    substr(strtolower((string) $slugger->slug($dossier->venue ?? 'concert')), 0, 70)
                : 'reference';
        $filename = 'fiche-technique-'.$band.'-'.$scope.'.pdf';
        $response = new Response($this->renderer->render($dossier));
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename,
            ),
        );
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
