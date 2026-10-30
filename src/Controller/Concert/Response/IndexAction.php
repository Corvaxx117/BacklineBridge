<?php

declare(strict_types=1);

namespace App\Controller\Concert\Response;

use App\Entity\Concert;
use App\Enum\Availability;
use App\Security\Voter\ConcertVoter;
use App\Service\Venue\VenueWorkspace;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/concerts/{id}/reponses-salle',
    name: 'app_concert_responses',
    requirements: ['id' => '\d+'],
    methods: ['GET'],
),]
final class IndexAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Concert $concert,
        VenueWorkspace $workspace,
    ): Response {
        $this->denyAccessUnlessGranted(ConcertVoter::VIEW, $concert);

        return $this->render('concert/responses.html.twig', [
            'concert' => $concert,
            'invitations' => $workspace->invitations($concert),
            'statusLabels' => array_combine(
                array_column(Availability::cases(), 'value'),
                array_map(
                    static fn (Availability $status): string => $status->label(),
                    Availability::cases(),
                ),
            ),
        ]);
    }
}
