<?php

declare(strict_types=1);

namespace App\Controller\Concert;

use App\Entity\Concert;
use App\Entity\User;
use App\Security\Voter\ConcertVoter;
use App\Service\Concert\ConcertViewBuilder;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/concerts/{id}', name: 'app_concert_show', requirements: ['id' => '\d+'], methods: ['GET'])]
final class ShowAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Concert $concert,
        #[CurrentUser] User $user,
        ConcertViewBuilder $view,
    ): Response {
        $this->denyAccessUnlessGranted(ConcertVoter::VIEW, $concert);

        return $this->render('concert/show.html.twig', $view->build($concert, $user));
    }
}
