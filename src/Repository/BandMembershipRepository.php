<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Band;
use App\Entity\BandMembership;
use App\Entity\User;
use App\Enum\BandRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BandMembership> */
class BandMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BandMembership::class);
    }

    public function roleFor(User $user, Band $band): ?BandRole
    {
        // Relire l'adhésion à chaque décision prend en compte sa révocation, même en session active.
        return $this->findOneBy(['user' => $user, 'band' => $band])?->getRole();
    }

    public function firstRoleFor(User $user): ?BandRole
    {
        return $this->findOneBy(['user' => $user], ['band' => 'ASC'])?->getRole();
    }

    /** @return list<BandMembership> */
    public function managersFor(Band $band): array
    {
        return $this->createQueryBuilder('membership')
            ->addSelect('person')
            ->join('membership.user', 'person')
            ->andWhere('membership.band = :band')
            ->andWhere('membership.role = :role')
            ->setParameter('band', $band)
            ->setParameter('role', BandRole::Manager)
            ->orderBy('person.displayName', 'ASC')
            ->addOrderBy('person.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
