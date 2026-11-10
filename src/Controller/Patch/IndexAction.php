<?php

declare(strict_types=1);

namespace App\Controller\Patch;

use App\Entity\PatchLine;
use App\Entity\User;
use App\Repository\PatchLineRepository;
use App\Repository\TechnicalSheetRepository;
use App\Security\Voter\TechnicalSheetVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/', name: 'app_patch_index', methods: ['GET'])]
final class IndexAction extends AbstractController
{
    public function __invoke(
        #[CurrentUser] User $user,
        TechnicalSheetRepository $sheets,
        PatchLineRepository $lines,
    ): Response {
        $sheet =
            $sheets->findReferenceForUser($user) ??
            throw $this->createAccessDeniedException('Aucun accès à une fiche de groupe.');
        $this->denyAccessUnlessGranted(TechnicalSheetVoter::VIEW, $sheet);
        $patchLines = $lines->findForSheet($sheet);
        $sections = array_unique(
            array_map(static fn (PatchLine $line): string => $line->getSection(), $patchLines),
        );

        return $this->render('patch/index.html.twig', [
            'sheet' => $sheet,
            'lines' => $patchLines,
            'sectionCount' => count($sections),
        ]);
    }
}
