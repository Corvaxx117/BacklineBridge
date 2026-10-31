<?php

declare(strict_types=1);

namespace App\Controller\Document;

use App\Entity\Band;
use App\Enum\DocumentCategory;
use App\Repository\BandDocumentRepository;
use App\Security\Voter\BandVoter;
use App\Service\Document\DocumentStorage;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/groupes/{id}/documents',
    name: 'app_document_index',
    requirements: ['id' => '\d+'],
    methods: ['GET'],
)]
final class IndexAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] Band $band,
        BandDocumentRepository $documents,
        DocumentStorage $storage,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::VIEW, $band);
        $search = mb_substr(trim($request->query->getString('q')), 0, 160);
        $category = DocumentCategory::tryFrom($request->query->getString('category'));

        return $this->render('document/index.html.twig', [
            'band' => $band,
            'documents' => $documents->forBand($band, $search, $category),
            'categories' => DocumentCategory::cases(),
            'category' => $category,
            'search' => $search,
            'usedBytes' => $storage->usedBytes($band),
            'quotaBytes' => $storage->getQuotaBytes(),
        ]);
    }
}
