<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\{MemberProfile, User};
use App\Enum\{BandRole, MemberKind};
use App\Repository\{BandMembershipRepository, MemberProfileRepository};
use App\Service\Finance\MusicianAccess;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\{Vote, Voter};

/** @extends Voter<string, MemberProfile> */
final class MemberProfileVoter extends Voter
{
    public const VIEW = 'MEMBER_PROFILE_VIEW';
    public function __construct(
        private readonly BandMembershipRepository $memberships,
        private readonly MemberProfileRepository $profiles,
        private readonly MusicianAccess $musicians,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof MemberProfile;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        $user = $token->getUser();
        if (
            !($user instanceof User) ||
            ($role = $this->memberships->roleFor($user, $subject->getBand())) === null
        ) {
            return false;
        }
        return $subject->getUser()?->getId() === $user->getId() ||
            $this->musicians->allows($user, $subject->getBand()) ||
            ($role === BandRole::Booker &&
                $this->profiles->forUser($user, $subject->getBand())?->getKind() ===
                    MemberKind::Booker);
    }
}
