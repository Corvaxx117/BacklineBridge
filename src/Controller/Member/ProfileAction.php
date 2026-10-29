<?php

declare(strict_types=1);

namespace App\Controller\Member;

use App\Entity\MemberProfile;
use App\Security\Voter\MemberProfileVoter;
use App\Service\Member\ProfileCipher;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[
    Route(
        '/equipe/{publicId}',
        name: 'app_profile_show',
        requirements: ['publicId' => '[a-f0-9]{32}'],
        methods: ['GET'],
    ),
]
final class ProfileAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(mapping: ['publicId' => 'publicId'])] MemberProfile $profile,
        ProfileCipher $cipher,
    ): Response {
        $this->denyAccessUnlessGranted(MemberProfileVoter::VIEW, $profile);
        return $this->render('member/profile.html.twig', [
            'band' => $profile->getBand(),
            'profile' => $profile,
            'administrative' => $cipher->decrypt($profile),
        ]);
    }
}
