<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TechnicalSheet;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TechnicalSheet> */
class TechnicalSheetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TechnicalSheet::class);
    }

    public function findReference(): ?TechnicalSheet
    {
        return $this->createQueryBuilder('sheet')
            ->leftJoin('sheet.concert', 'concert')
            ->andWhere('concert.id IS NULL')
            ->orderBy('sheet.id', \SortDirection::Ascending)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findReferenceForUser(User $user): ?TechnicalSheet
    {
        return $this->createQueryBuilder('sheet')
            ->join('sheet.referenceBand', 'band')
            ->join('band.memberships', 'membership')
            ->andWhere('membership.user = :user')
            ->setParameter('user', $user)
            ->orderBy('band.id', \SortDirection::Ascending)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
