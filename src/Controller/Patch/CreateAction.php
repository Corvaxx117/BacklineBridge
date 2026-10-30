<?php

declare(strict_types=1);

namespace App\Controller\Patch;

use App\Entity\PatchLine;
use App\Entity\User;
use App\Form\PatchLineType;
use App\Repository\TechnicalSheetRepository;
use App\Security\Voter\TechnicalSheetVoter;
use App\Service\Patch\FormSubmissionResult;
use App\Service\Patch\PatchLineFormHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/sources/new', name: 'app_patch_new', methods: ['GET', 'POST'])]
final class CreateAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[CurrentUser] User $user,
        TechnicalSheetRepository $sheets,
        PatchLineFormHandler $handler,
    ): Response {
        $sheet =
            $sheets->findReferenceForUser($user) ??
            throw $this->createAccessDeniedException('Aucun accès à une fiche de groupe.');
        $this->denyAccessUnlessGranted(TechnicalSheetVoter::EDIT, $sheet);
        $line = new PatchLine()->setSheet($sheet);
        $form = $this->createForm(PatchLineType::class, $line);
        $form->handleRequest($request);
        $result = $handler->handle($form, $line);

        if (FormSubmissionResult::Saved === $result) {
            $this->addFlash('success', 'La source a été ajoutée au patch.');

            return $this->redirectToRoute('app_patch_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render(
            'patch/form.html.twig',
            [
                'sheet' => $sheet,
                'line' => $line,
                'form' => $form->createView(),
                'editing' => false,
            ],
            new Response(status: $result->value),
        );
    }
}
