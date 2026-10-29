<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Concert;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Concert> */
class ConcertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Concert::class);
    }

    /** @return list<Concert> */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('concert')
            ->addSelect('sheet')
            ->join('concert.patchSheet', 'sheet')
            ->join('concert.band', 'band')
            ->join('band.memberships', 'membership')
            ->andWhere('membership.user = :user')
            ->setParameter('user', $user)
            ->orderBy('concert.date', \SortDirection::Ascending)
            ->addOrderBy('concert.id', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }
}
