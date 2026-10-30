<?php

declare(strict_types=1);

namespace App\Controller\Concert;

use App\Dto\Concert\CreateConcertInput;
use App\Entity\Concert;
use App\Form\ConcertType;
use App\Security\Voter\ConcertVoter;
use App\Service\Concert\ConcertUpdater;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/concerts/{id}/edit',
    name: 'app_concert_edit',
    requirements: ['id' => '\d+'],
    methods: ['GET', 'POST'],
),]
final class EditAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] Concert $concert,
        ConcertUpdater $updater,
    ): Response {
        $this->denyAccessUnlessGranted(ConcertVoter::UPDATE, $concert);
        $input = CreateConcertInput::fromConcert($concert);
        $form = $this->createForm(ConcertType::class, $input, ['editing' => true]);
        $form->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $dateChanged = $updater->update($concert, $input);
                $this->addFlash(
                    'success',
                    $dateChanged
                        ? 'La date a changé : chaque responsable doit confirmer à nouveau sa disponibilité.'
                        : 'Les informations du concert ont été enregistrées.',
                );

                return $this->redirectToRoute(
                    'app_concert_show',
                    ['id' => $concert->getId()],
                    Response::HTTP_SEE_OTHER,
                );
            } catch (ConflictHttpException|OptimisticLockException) {
                $form->addError(
                    new FormError(
                        'Ce concert a été modifié depuis l’ouverture du formulaire. Rien n’a été enregistré. '.
                            'Tes valeurs restent affichées : conserve-les si nécessaire, puis recharge la version actuelle.',
                    ),
                );
                $status = 409;
            }
        }

        return $this->render(
            'concert/form.html.twig',
            [
                'sheet' => $concert->getPatchSheet(),
                'concert' => $concert,
                'form' => $form->createView(),
                'editing' => true,
                'conflict' => 409 === $status,
            ],
            new Response(status: $status),
        );
    }
}
