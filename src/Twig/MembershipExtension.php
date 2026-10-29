<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Band;
use App\Entity\User;
use App\Enum\BandRole;
use App\Repository\BandMembershipRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MembershipExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly BandMembershipRepository $memberships,
    ) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('band_role', $this->role(...))];
    }

    public function role(?Band $band = null): ?BandRole
    {
        $user = $this->security->getUser();
        if (!($user instanceof User)) {
            return null;
        }

        return $band === null
            ? $this->memberships->firstRoleFor($user)
            : $this->memberships->roleFor($user, $band);
    }
}
