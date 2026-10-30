<?php

declare(strict_types=1);

namespace App\Controller\Merch;

use App\Entity\Band;
use App\Repository\MerchRepository;
use App\Security\Voter\BandVoter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/groupes/{id}/stock',
    name: 'app_stock_index',
    requirements: ['id' => '\d+'],
    methods: ['GET'],
),]
final class IndexAction extends AbstractController
{
    public function __invoke(#[MapEntity(id: 'id')] Band $band, MerchRepository $merch): Response
    {
        $this->denyAccessUnlessGranted(BandVoter::TREASURY, $band);

        return $this->render('merch/index.html.twig', [
            'band' => $band,
            'products' => $merch->products($band),
            'movements' => $merch->history($band),
        ]);
    }
}
