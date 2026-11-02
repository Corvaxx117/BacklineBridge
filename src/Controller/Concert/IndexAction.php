<?php

declare(strict_types=1);

namespace App\Controller\Concert;

use App\Entity\User;
use App\Repository\ConcertAvailabilityRepository;
use App\Repository\ConcertRepository;
use App\Repository\TechnicalSheetRepository;
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
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));
        $upcoming = [];
        $past = [];
        foreach ($dates as $concert) {
            if ($concert->getDate()->format('Y-m-d') < $today->format('Y-m-d')) {
                $past[] = $concert;
            } else {
                $upcoming[] = $concert;
            }
        }

        return $this->render('concert/index.html.twig', [
            'concerts' => $dates,
            'upcomingConcerts' => $upcoming,
            'pastConcerts' => array_reverse($past),
            'band' => $reference?->getBand(),
            'myAvailabilities' => $availability->forUserAndConcerts($user, $upcoming),
            'canCreate' => null !== $reference
                && $this->isGranted(BandVoter::PLAN_CONCERT, $reference->getBand()),
        ]);
    }
}
