<?php

declare(strict_types=1);

namespace App\Controller\Stage;

use App\Dto\Stage\StagePlanInput;
use App\Entity\TechnicalSheet;
use App\Form\Stage\StagePlanType;
use App\Security\Voter\TechnicalSheetVoter;
use App\Service\Stage\StagePatchLinks;
use App\Service\Stage\TechnicalSetupUpdater;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/fiches/{id}/plan-de-scene',
    name: 'app_stage_edit',
    requirements: ['id' => '\d+'],
    methods: ['GET', 'POST'],
),]
final class EditAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] TechnicalSheet $sheet,
        TechnicalSetupUpdater $updater,
        StagePatchLinks $links,
    ): Response {
        $this->denyAccessUnlessGranted(TechnicalSheetVoter::MANAGE, $sheet);
        $input = StagePlanInput::fromSheet($sheet);
        $patchChoices = $links->choices($sheet);
        $formChoices = [];
        foreach ($patchChoices as $key => $choice) {
            $formChoices[$choice['label']] = $key;
        }
        $form = $this->createForm(StagePlanType::class, $input, ['patch_choices' => $formChoices])
            ->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            if (!hash_equals(StagePlanInput::fingerprint($sheet), $input->revision)) {
                $form->addError(
                    new FormError(
                        'Ce plan a changé, ou son patch a été modifié. Rechargez la page avant de réessayer.',
                    ),
                );
                $status = 409;
            } else {
                $updater->stage($sheet, $input);
                $this->addFlash(
                    'success',
                    'Le plan de scène a été enregistré. Les versions déjà publiées sont conservées.',
                );

                return $sheet->getConcert()
                    ? $this->redirectToRoute(
                        'app_concert_show',
                        ['id' => $sheet->getConcert()->getId()],
                        303,
                    )
                    : $this->redirectToRoute('app_patch_index', status: 303);
            }
        }

        return $this->render(
            'stage/edit.html.twig',
            ['sheet' => $sheet, 'form' => $form->createView(), 'patchChoices' => $patchChoices],
            new Response(status: $status),
        );
    }
}
