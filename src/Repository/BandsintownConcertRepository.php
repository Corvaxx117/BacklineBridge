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

    /** @return list<array{artist_id: string, event_id: string, concert_id: int}> */
    public function identitiesForBand(Band $band): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT artist_id, event_id, concert_id FROM bandsintown_concert WHERE band_id = ?',
            [$band->getId()],
        );

        return array_map(static fn (array $row): array => [
            'artist_id' => $row['artist_id'],
            'event_id' => $row['event_id'],
            'concert_id' => (int) $row['concert_id'],
        ], $rows);
    }

    public function isImported(Concert $concert): bool
    {
        return $this->count(['concert' => $concert]) > 0;
    }

    public function forConcert(Concert $concert): ?BandsintownConcert
    {
        return $this->findOneBy(['concert' => $concert]);
    }
}
