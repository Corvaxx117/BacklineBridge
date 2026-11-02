<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Concert\ConcertCard;
use App\Entity\Band;
use App\Entity\Concert;
use App\Entity\User;
use App\Enum\AvailabilityStatus;
use App\Enum\BandRole;
use App\Enum\ConcertStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Concert> */
class ConcertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Concert::class);
    }

    /** @return list<ConcertCard> */
    public function findCardsForUser(User $user): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT concert.id, concert.date, concert.venue, concert.city, concert.status,
                    concert.created_by_name, sheet.band_name, membership.role,
                    availability.status AS availability_status
                FROM concert
                JOIN technical_sheet sheet ON sheet.id = concert.patch_sheet_id
                JOIN band_membership membership ON membership.band_id = concert.band_id
                    AND membership.user_id = :user
                LEFT JOIN concert_availability availability ON availability.concert_id = concert.id
                    AND availability.user_id = :user
                ORDER BY concert.date ASC, concert.id ASC
                SQL,
            ['user' => $user->getId()],
        );

        return array_map(static fn (array $row): ConcertCard => new ConcertCard(
            id: (int) $row['id'],
            date: new \DateTimeImmutable($row['date']),
            venue: $row['venue'],
            city: $row['city'],
            status: ConcertStatus::from($row['status']),
            bandName: $row['band_name'],
            createdByName: $row['created_by_name'],
            canRespond: BandRole::Manager->value === $row['role'],
            myStatus: null === $row['availability_status'] ? null : AvailabilityStatus::from($row['availability_status']),
        ), $rows);
    }

    /** @return list<array{id: int, date: string, venue: string, city: string}> */
    public function identitiesForBand(Band $band): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT id, date, venue, city FROM concert WHERE band_id = ? ORDER BY id ASC',
            [$band->getId()],
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'date' => $row['date'],
            'venue' => $row['venue'],
            'city' => $row['city'],
        ], $rows);
    }
}
