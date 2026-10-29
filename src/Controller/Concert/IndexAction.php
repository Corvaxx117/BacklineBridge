<?php

declare(strict_types=1);

namespace App\Controller\Concert;

use App\Entity\User;
use App\Repository\ConcertRepository;
use App\Repository\TechnicalSheetRepository;
use App\Repository\ConcertAvailabilityRepository;
use App\Security\Voter\BandVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/concerts', name: 'app_concert_index', methods: ['GET'])]
final class IndexAction extends AbstractController
{
    public function __invoke(
        #[CurrentUser] User $user,
        ConcertRepository $concerts,
        TechnicalSheetRepository $sheets,
        ConcertAvailabilityRepository $availability,
    ): Response {
        $reference = $sheets->findReferenceForUser($user);
        $dates = $concerts->findForUser($user);

        return $this->render('concert/index.html.twig', [
            'concerts' => $dates,
            'band' => $reference?->getBand(),
            'myAvailabilities' => $availability->forUserAndConcerts($user, $dates),
            'canCreate' =>
                $reference !== null &&
                $this->isGranted(BandVoter::PLAN_CONCERT, $reference->getBand()),
        ]);
    }
}
