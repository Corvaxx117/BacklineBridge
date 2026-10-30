<?php

declare(strict_types=1);

namespace App\Controller\Equipment;

use App\Dto\Stage\EquipmentInput;
use App\Entity\TechnicalSheet;
use App\Form\Stage\EquipmentType;
use App\Security\Voter\TechnicalSheetVoter;
use App\Service\Stage\TechnicalSetupUpdater;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/fiches/{id}/materiel',
    name: 'app_equipment_edit',
    requirements: ['id' => '\d+'],
    methods: ['GET', 'POST'],
),]
final class EditAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] TechnicalSheet $sheet,
        TechnicalSetupUpdater $updater,
    ): Response {
        $this->denyAccessUnlessGranted(TechnicalSheetVoter::MANAGE, $sheet);
        $input = EquipmentInput::fromSheet($sheet);
        $form = $this->createForm(EquipmentType::class, $input)->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            if (!hash_equals(EquipmentInput::fingerprint($sheet), $input->revision)) {
                $form->addError(
                    new FormError(
                        'Cette liste a changé depuis son ouverture. Rechargez la page avant de réessayer.',
                    ),
                );
                $status = 409;
            } else {
                $updater->equipment($sheet, $input);
                $this->addFlash(
                    'success',
                    'La liste de matériel a été enregistrée. Les versions déjà publiées sont conservées.',
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
            'equipment/edit.html.twig',
            ['sheet' => $sheet, 'form' => $form->createView()],
            new Response(status: $status),
        );
    }
}
