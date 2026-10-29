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

    public function band(?Band $band = null): ?Band
    {
        $user = $this->security->getUser();
        if (!($user instanceof User)) {
            return null;
        }
        // Charger le groupe via l'adhésion courante évite les proxys détachés après un conflit ORM.
        $query = $this->memberships
            ->createQueryBuilder('membership')
            ->addSelect('band')
            ->join('membership.band', 'band')
            ->where('membership.user = :user')
            ->setParameter('user', $user)
            ->orderBy('band.id', 'ASC')
            ->setMaxResults(1);
        if ($band !== null) {
            $query->andWhere('membership.band = :band')->setParameter('band', $band);
        }
        return $query->getQuery()->getOneOrNullResult()?->getBand();
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('band_role', $this->role(...)),
            new TwigFunction('member_band', $this->band(...)),
        ];
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
