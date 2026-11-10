<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\Band;
use App\Entity\User;
use App\Enum\BandRole;
use App\Repository\BandMembershipRepository;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/** Publication dans l’agenda et fiches administratives ; les finances gardent MusicianAccess. */
final readonly class MusicianOrManagerAccess
{
    public function __construct(
        private MusicianAccess $musicians,
        private BandMembershipRepository $memberships,
    ) {
    }

    public function allows(User $user, Band $band): bool
    {
        return BandRole::TeamManager === $this->memberships->roleFor($user, $band)
            || $this->musicians->allows($user, $band);
    }

    public function assert(User $user, Band $band): void
    {
        if (!$this->allows($user, $band)) {
            throw new AccessDeniedException('Cet accès est réservé aux musiciens et au manager du groupe.');
        }
    }
}
