<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Entity\{Band, User};
use App\Enum\BandRole;
use App\Repository\{BandMembershipRepository, MemberProfileRepository};
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class MusicianAccess
{
    public function __construct(
        private BandMembershipRepository $memberships,
        private MemberProfileRepository $profiles,
    ) {}

    public function allows(User $user, Band $band): bool
    {
        return $this->memberships->roleFor($user, $band) === BandRole::Manager &&
            $this->profiles->isMusician($user, $band);
    }

    public function assert(User $user, Band $band): void
    {
        if (!$this->allows($user, $band)) {
            throw new AccessDeniedException(
                'Cet espace est réservé aux membres musiciens du groupe.',
            );
        }
    }
}
