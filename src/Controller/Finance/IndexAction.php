<?php

declare(strict_types=1);

namespace App\Controller\Finance;

use App\Entity\Band;
use App\Repository\TreasuryRepository;
use App\Security\Voter\BandVoter;
use App\Service\Finance\TreasuryView;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/groupes/{id}/tresorerie',
    name: 'app_treasury_index',
    requirements: ['id' => '\d+'],
    methods: ['GET'],
),]
final class IndexAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Band $band,
        Request $request,
        TreasuryView $view,
        TreasuryRepository $treasury,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::TREASURY, $band);
        $pages = max(1, (int) ceil($treasury->countEntries($band) / 25));
        $page = min($pages, max(1, $request->query->getInt('page', 1)));

        return $this->render('finance/index.html.twig', [
            'band' => $band,
            'summary' => $view->summary($band),
            'entries' => $treasury->entries($band, $page),
            'notes' => $treasury->notes($band),
            'page' => $page,
            'pages' => $pages,
        ]);
    }
}
