<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Band;
use App\Entity\Concert;
use App\Entity\OrfeoConcert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OrfeoConcert> */
final class OrfeoConcertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrfeoConcert::class);
    }

    public function forConcert(Concert $concert): ?OrfeoConcert
    {
        return $this->findOneBy(['concert' => $concert]);
    }

    /** @return list<array{id: int, project_id: int, performance_id: int, concert_id: int, revision: int}> */
    public function identitiesForBand(Band $band): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT source.id, source.project_id, source.performance_id, source.concert_id, source.revision
                FROM orfeo_concert source JOIN concert ON concert.id = source.concert_id
                WHERE concert.band_id = ? ORDER BY source.id', [$band->getId()],
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'project_id' => (int) $row['project_id'],
            'performance_id' => (int) $row['performance_id'],
            'concert_id' => (int) $row['concert_id'],
            'revision' => (int) $row['revision'],
        ], $rows);
    }
}
