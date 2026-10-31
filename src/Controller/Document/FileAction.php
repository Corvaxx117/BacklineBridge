<?php

declare(strict_types=1);

namespace App\Controller\Document;

use App\Entity\BandDocument;
use App\Security\Voter\DocumentVoter;
use App\Service\Document\DocumentStorage;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/documents/{id}/fichier',
    name: 'app_document_file',
    requirements: ['id' => '\d+'],
    methods: ['GET'],
)]
final class FileAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] BandDocument $document,
        DocumentStorage $storage,
    ): BinaryFileResponse {
        $this->denyAccessUnlessGranted(DocumentVoter::VIEW, $document);
        if (!$document->isPdf()) {
            throw $this->createNotFoundException();
        }
        $path = $storage->path($document);
        if (!is_file($path) || !is_readable($path)) {
            throw $this->createNotFoundException('Ce PDF est momentanément indisponible.');
        }
        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(
            $request->query->getBoolean('download')
                ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
                : ResponseHeaderBag::DISPOSITION_INLINE,
            $document->getOriginalFilename() ?: 'document.pdf',
            'document.pdf',
        );
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
