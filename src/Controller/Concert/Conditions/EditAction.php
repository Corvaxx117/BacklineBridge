<?php

declare(strict_types=1);

namespace App\Controller\Concert\Conditions;

use App\Dto\Concert\ConcertConditionsInput;
use App\Entity\Concert;
use App\Entity\ConcertConditions;
use App\Entity\OrfeoConcert;
use App\Form\ConcertConditionsType;
use App\Security\Voter\ConcertVoter;
use App\Service\Concert\ConcertConditionsUpdater;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/concerts/{id}/conditions',
    name: 'app_concert_conditions',
    requirements: ['id' => '\d+'],
    methods: ['GET', 'POST'],
),]
final class EditAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[MapEntity(id: 'id')] Concert $concert,
        EntityManagerInterface $entityManager,
        ConcertConditionsUpdater $updater,
    ): Response {
        $this->denyAccessUnlessGranted(ConcertVoter::CONDITIONS, $concert);
        $input = ConcertConditionsInput::fromConditions(
            $entityManager
                ->getRepository(ConcertConditions::class)
                ->findOneBy(['concert' => $concert]),
            $concert->getDate()->format('Y-m-d'),
        );
        $form = $this->createForm(ConcertConditionsType::class, $input);
        $form->handleRequest($request);
        $status = $form->isSubmitted() ? 422 : 200;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $updater->save($concert, $input);
                $this->addFlash('success', 'Les conditions du concert ont été enregistrées.');

                return $this->redirectToRoute(
                    'app_concert_show',
                    ['id' => $concert->getId(), '_fragment' => 'concert-conditions'],
                    Response::HTTP_SEE_OTHER,
                );
            } catch (UniqueConstraintViolationException) {
                $this->addFlash(
                    'error',
                    'Les conditions viennent d’être créées dans une autre session. Relisez-les avant de les modifier.',
                );

                return $this->redirectToRoute(
                    'app_concert_conditions',
                    ['id' => $concert->getId()],
                    Response::HTTP_SEE_OTHER,
                );
            } catch (ConflictHttpException|RetryableException $exception) {
                $form->addError(new FormError($exception instanceof ConflictHttpException
                    ? $exception->getMessage() : 'Une modification est en cours. Recharge les conditions puis réessaie.'));
                $status = 409;
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render(
            'concert/conditions.html.twig',
            [
                'concert' => $concert,
                'form' => $form->createView(),
                'orfeoSource' => $entityManager->getRepository(OrfeoConcert::class)->findOneBy(['concert' => $concert]),
            ],
            new Response(status: $status),
        );
    }
}
