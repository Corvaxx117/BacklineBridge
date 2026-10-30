<?php

declare(strict_types=1);

namespace App\Controller\Patch;

use App\Entity\PatchLine;
use App\Form\PatchLineType;
use App\Security\Voter\TechnicalSheetVoter;
use App\Service\Patch\FormSubmissionResult;
use App\Service\Patch\PatchLineFormHandler;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/sources/{id}/edit',
    name: 'app_patch_edit',
    requirements: ['id' => '\\d+'],
    methods: ['GET', 'POST'],
),]
final class EditAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] PatchLine $line,
        PatchLineFormHandler $handler,
    ): Response {
        $sheet =
            $line->getSheet() ??
            throw $this->createNotFoundException('La fiche de cette source est introuvable.');
        $this->denyAccessUnlessGranted(TechnicalSheetVoter::EDIT, $sheet);
        if (!$line->isActive()) {
            throw $this->createNotFoundException('Cette source est dans la corbeille.');
        }
        $concert = $sheet->getConcert();
        $form = $this->createForm(PatchLineType::class, $line);
        $form->handleRequest($request);
        $result = $handler->handle($form, $line);

        if (FormSubmissionResult::Saved === $result) {
            $this->addFlash('success', 'La source a été mise à jour.');

            if (null !== $concert) {
                return $this->redirectToRoute(
                    'app_concert_show',
                    ['id' => $concert->getId()],
                    Response::HTTP_SEE_OTHER,
                );
            }

            return $this->redirectToRoute('app_patch_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render(
            'patch/form.html.twig',
            [
                'sheet' => $sheet,
                'line' => $line,
                'form' => $form->createView(),
                'editing' => true,
            ],
            new Response(status: $result->value),
        );
    }
}
