<?php

declare(strict_types=1);

namespace App\Controller\Concert\Importance;

use App\Dto\Concert\ConcertImportanceInput;
use App\Dto\Concert\ConcertListFilters;
use App\Entity\Concert;
use App\Form\Concert\ConcertImportanceType;
use App\Security\Voter\ConcertVoter;
use App\Service\Concert\ConcertImportanceUpdater;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/concerts/{id}/enjeu', name: 'app_concert_importance', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
final class EditAction extends AbstractController
{
    public function __invoke(#[MapEntity(id: 'id')] Concert $concert, Request $request, ConcertImportanceUpdater $updater): Response
    {
        $this->denyAccessUnlessGranted(ConcertVoter::UPDATE, $concert);
        $input = ConcertImportanceInput::fromConcert($concert);
        $filters = ConcertListFilters::fromQuery($request->query->all());
        $input->returnSort = $filters->sort;
        $input->returnLevel = $filters->level;
        $form = $this->createForm(ConcertImportanceType::class, $input,
            ['csrf_token_id' => 'concert_importance_'.$concert->getId()])->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $updater->update($concert, $input);
                $this->addFlash('success', 'L’enjeu du concert a été enregistré.');
                if ('concert' === $input->returnTo) {
                    return $this->redirectToRoute('app_concert_show', ['id' => $concert->getId(), '_fragment' => 'enjeu-'.$concert->getId()], 303);
                }

                return $this->redirectToRoute('app_concert_index', ['sort' => $input->returnSort,
                    'enjeu' => $input->returnLevel, '_fragment' => 'concert-'.$concert->getId()], 303);
            } catch (ConflictHttpException|OptimisticLockException) {
                $form->addError(new FormError('Ce concert a changé depuis l’ouverture du formulaire. Ta note reste affichée ; recharge la version actuelle avant de réessayer.'));
                $status = 409;
            }
        }

        return $this->render('concert/importance.html.twig', ['concert' => $concert, 'sheet' => $concert->getPatchSheet(),
            'form' => $form->createView()], new Response(status: $status));
    }
}
