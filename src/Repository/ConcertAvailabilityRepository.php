<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Concert;
use App\Entity\ConcertAvailability;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ConcertAvailability> */
final class ConcertAvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConcertAvailability::class);
    }

    /** @return list<ConcertAvailability> */
    public function forConcert(Concert $concert): array
    {
        return $this->createQueryBuilder('availability')
            ->addSelect('person')
            ->join('availability.user', 'person')
            ->andWhere('availability.concert = :concert')
            ->setParameter('concert', $concert)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<Concert> $concerts
     *
     * @return array<int, ConcertAvailability>
     */
    public function forUserAndConcerts(User $user, array $concerts): array
    {
        if ([] === $concerts) {
            return [];
        }

        $records = $this->createQueryBuilder('availability')
            ->andWhere('availability.user = :user')
            ->andWhere('availability.concert IN (:concerts)')
            ->setParameter('user', $user)
            ->setParameter('concerts', $concerts)
            ->getQuery()
            ->getResult();
        $indexed = [];
        foreach ($records as $record) {
            $id =
                $record->getConcert()->getId() ??
                throw new \LogicException('Un concert enregistré doit avoir un identifiant.');
            $indexed[$id] = $record;
        }

        return $indexed;
    }
}
