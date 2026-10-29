<?php

declare(strict_types=1);

namespace App\Controller\Concert;

use App\Dto\Concert\CreateConcertInput;
use App\Entity\User;
use App\Form\ConcertType;
use App\Repository\TechnicalSheetRepository;
use App\Security\Voter\BandVoter;
use App\Service\Concert\ConcertCreator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/concerts/new', name: 'app_concert_new', methods: ['GET', 'POST'])]
final class CreateAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[CurrentUser] User $user,
        TechnicalSheetRepository $sheets,
        ConcertCreator $creator,
    ): Response {
        $reference =
            $sheets->findReferenceForUser($user) ??
            throw $this->createAccessDeniedException('Aucun accès à une fiche de groupe.');
        $this->denyAccessUnlessGranted(BandVoter::PLAN_CONCERT, $reference->getBand());
        $input = new CreateConcertInput();
        $form = $this->createForm(ConcertType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $concert = $creator->create(
                $reference,
                $input->date,
                $input->venue,
                $input->city,
                $input->status,
                $input->notes,
                $user,
            );
            $this->addFlash(
                'success',
                'Le concert est créé. Chacun peut maintenant confirmer sa disponibilité pour cette date.',
            );

            return $this->redirectToRoute(
                'app_concert_show',
                ['id' => $concert->getId()],
                Response::HTTP_SEE_OTHER,
            );
        }

        return $this->render(
            'concert/form.html.twig',
            [
                'sheet' => $reference,
                'form' => $form->createView(),
                'editing' => false,
                'concert' => null,
            ],
            new Response(
                status: $form->isSubmitted()
                    ? Response::HTTP_UNPROCESSABLE_ENTITY
                    : Response::HTTP_OK,
            ),
        );
    }
}
