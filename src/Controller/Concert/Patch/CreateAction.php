<?php

declare(strict_types=1);

namespace App\Controller\Concert\Patch;

use App\Entity\Concert;
use App\Entity\PatchLine;
use App\Form\PatchLineType;
use App\Security\Voter\ConcertVoter;
use App\Service\Patch\FormSubmissionResult;
use App\Service\Patch\PatchLineFormHandler;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/concerts/{id}/sources/new',
    name: 'app_concert_patch_new',
    requirements: ['id' => '\d+'],
    methods: ['GET', 'POST'],
),]
final class CreateAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] Concert $concert,
        PatchLineFormHandler $handler,
    ): Response {
        $this->denyAccessUnlessGranted(ConcertVoter::EDIT, $concert);
        $sheet = $concert->getPatchSheet();
        $line = new PatchLine()->setSheet($sheet);
        $form = $this->createForm(PatchLineType::class, $line);
        $form->handleRequest($request);
        $result = $handler->handle($form, $line);

        if (FormSubmissionResult::Saved === $result) {
            $this->addFlash('success', 'La source a été ajoutée au patch de ce concert.');

            return $this->redirectToRoute(
                'app_concert_show',
                ['id' => $concert->getId()],
                Response::HTTP_SEE_OTHER,
            );
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
