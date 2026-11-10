<?php

declare(strict_types=1);

namespace App\Controller\Concert;

use App\Dto\Concert\ConcertListFilters;
use App\Entity\User;
use App\Enum\ConcertImportance;
use App\Repository\ConcertRepository;
use App\Repository\TechnicalSheetRepository;
use App\Security\Voter\BandVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/concerts', name: 'app_concert_index', methods: ['GET'])]
final class IndexAction extends AbstractController
{
    public function __invoke(
        #[CurrentUser] User $user,
        Request $request,
        ConcertRepository $concerts,
        TechnicalSheetRepository $sheets,
    ): Response {
        $reference = $sheets->findReferenceForUser($user);
        $filters = ConcertListFilters::fromQuery($request->query->all());
        $dates = $concerts->findCardsForUser($user, $filters);
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));
        $upcoming = [];
        $past = [];
        foreach ($dates as $concert) {
            if ($concert->date->format('Y-m-d') < $today->format('Y-m-d')) {
                $past[] = $concert;
            } else {
                $upcoming[] = $concert;
            }
        }

        return $this->render('concert/index.html.twig', [
            'concerts' => $dates,
            'upcomingConcerts' => $upcoming,
            'pastConcerts' => 'date' === $filters->sort ? array_reverse($past) : $past,
            'filters' => $filters,
            'importanceLevels' => ConcertImportance::cases(),
            'band' => $reference?->getBand(),
            'canCreate' => null !== $reference
                && $this->isGranted(BandVoter::PLAN_CONCERT, $reference->getBand()),
        ]);
    }
}
