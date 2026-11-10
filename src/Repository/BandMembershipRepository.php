<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Band;
use App\Entity\BandMembership;
use App\Entity\User;
use App\Enum\BandRole;
use App\Service\Security\ReadRequestCache;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BandMembership> */
class BandMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly ReadRequestCache $reads)
    {
        parent::__construct($registry, BandMembership::class);
    }

    public function roleFor(User $user, Band $band): ?BandRole
    {
        return $this->reads->remember(
            'role.'.$user->getId().'.'.$band->getId(),
            fn (): ?BandRole => $this->findOneBy(['user' => $user, 'band' => $band])?->getRole(),
        );
    }

    public function firstRoleFor(User $user): ?BandRole
    {
        return $this->reads->remember(
            'first_role.'.$user->getId(),
            fn (): ?BandRole => $this->findOneBy(['user' => $user], ['band' => 'ASC'])?->getRole(),
        );
    }

    /** @return list<BandMembership> */
    public function forBand(Band $band): array
    {
        return $this->createQueryBuilder('membership')
            ->addSelect('person')
            ->join('membership.user', 'person')
            ->where('membership.band = :band')
            ->setParameter('band', $band)
            ->getQuery()->getResult();
    }
}
