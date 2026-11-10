<?php

declare(strict_types=1);

namespace App\Controller\Technical;

use App\Dto\Technical\TechnicalNotesInput;
use App\Entity\TechnicalSheet;
use App\Form\Technical\TechnicalNotesType;
use App\Security\Voter\TechnicalSheetVoter;
use App\Service\Technical\TechnicalInformationUpdater;
use Doctrine\DBAL\Exception\RetryableException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/fiches/{id}/notes',
    name: 'app_technical_notes_edit',
    requirements: ['id' => '\d+'],
    methods: ['GET', 'POST'],
),]
final class NotesAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] TechnicalSheet $sheet,
        TechnicalInformationUpdater $updater,
    ): Response {
        $this->denyAccessUnlessGranted(TechnicalSheetVoter::MANAGE, $sheet);
        $form = $this->createForm(TechnicalNotesType::class, TechnicalNotesInput::fromSheet($sheet), [
            'csrf_token_id' => 'technical_notes_'.$sheet->getId(),
        ])->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $updater->notes($sheet, $form->getData());
                $this->addFlash('success', 'Les notes techniques ont été enregistrées.');
                $concert = $sheet->getConcert();

                return null === $concert
                    ? $this->redirectToRoute('app_patch_index', ['_fragment' => 'sheet-notes'], 303)
                    : $this->redirectToRoute(
                        'app_concert_show',
                        ['id' => $concert->getId(), '_fragment' => 'technical-notes'],
                        303,
                    );
            } catch (ConflictHttpException|RetryableException $exception) {
                $form->addError(new FormError($exception instanceof ConflictHttpException
                    ? $exception->getMessage() : 'Une autre modification est en cours. Recharge la page puis réessaie.'));
                $status = 409;
            }
        }

        return $this->render('technical/edit.html.twig', [
            'sheet' => $sheet,
            'form' => $form->createView(),
            'mode' => 'notes',
            'heading' => 'Notes techniques',
            'contactCount' => max(1, count($sheet->getBand()?->getTechnicalIdentity()->contacts ?? [])),
        ], new Response(status: $status));
    }
}
