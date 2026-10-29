<?php

declare(strict_types=1);

namespace App\Controller\Member;

use App\Entity\Band;
use App\Repository\MemberProfileRepository;
use App\Security\Voter\BandVoter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[
    Route(
        '/groupes/{id}/equipe',
        name: 'app_profile_team',
        requirements: ['id' => '\d+'],
        methods: ['GET'],
    ),
]
final class TeamAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Band $band,
        MemberProfileRepository $profiles,
    ): Response {
        $this->denyAccessUnlessGranted(BandVoter::VIEW, $band);
        return $this->render('member/team.html.twig', [
            'band' => $band,
            'profiles' => $profiles->forBand($band),
        ]);
    }
}
