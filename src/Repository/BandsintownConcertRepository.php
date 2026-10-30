<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Band;
use App\Entity\BandsintownConcert;
use App\Entity\Concert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BandsintownConcert> */
final class BandsintownConcertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BandsintownConcert::class);
    }

    /** @return list<BandsintownConcert> */
    public function forBand(Band $band): array
    {
        return $this->createQueryBuilder('source')
            ->addSelect('concert')
            ->join('source.concert', 'concert')
            ->where('source.band = :band')
            ->setParameter('band', $band)
            ->getQuery()
            ->getResult();
    }

    public function forConcert(Concert $concert): ?BandsintownConcert
    {
        return $this->findOneBy(['concert' => $concert]);
    }
}
