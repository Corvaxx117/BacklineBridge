<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Concert\ConcertCard;
use App\Dto\Concert\ConcertListFilters;
use App\Entity\Band;
use App\Entity\Concert;
use App\Entity\User;
use App\Enum\BandRole;
use App\Enum\ConcertImportance;
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
    public function findCardsForUser(User $user, ?ConcertListFilters $filters = null): array
    {
        $filters ??= ConcertListFilters::fromQuery([]);
        $params = ['user' => $user->getId()];
        $where = '';
        if ('' !== $filters->search) {
            $where = <<<'SQL'
                WHERE (
                    LOWER(concert.venue) LIKE :search ESCAPE '!'
                    OR LOWER(concert.city) LIKE :search ESCAPE '!'
                    OR LOWER(concert.notes) LIKE :search ESCAPE '!'
                    OR LOWER(sheet.band_name) LIKE :search ESCAPE '!'
                )
                SQL;
            // Une recherche littérale : % et _ ne deviennent pas des jokers SQL.
            $params['search'] = '%'.strtr(mb_strtolower($filters->search), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
        }
        $order = 'importance' === $filters->sort
            ? 'CASE WHEN concert.importance_level IS NULL THEN 1 ELSE 0 END ASC, concert.importance_level DESC, '
            : '';
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<SQL
                SELECT concert.id, concert.date, concert.venue, concert.city, concert.status,
                    concert.created_by_name, concert.importance_level, concert.importance_note, concert.revision,
                    sheet.band_name, membership.role
                FROM concert
                JOIN technical_sheet sheet ON sheet.id = concert.patch_sheet_id
                JOIN band_membership membership ON membership.band_id = concert.band_id
                    AND membership.user_id = :user
                {$where}
                ORDER BY {$order}concert.date ASC, concert.id ASC
                SQL,
            $params,
        );

        return array_map(static fn (array $row): ConcertCard => new ConcertCard(
            id: (int) $row['id'],
            date: new \DateTimeImmutable($row['date']),
            venue: $row['venue'],
            city: $row['city'],
            status: ConcertStatus::from($row['status']),
            bandName: $row['band_name'],
            createdByName: $row['created_by_name'],
            importance: null === $row['importance_level'] ? null : ConcertImportance::from((int) $row['importance_level']),
            importanceNote: $row['importance_note'],
            revision: (int) $row['revision'],
            canManageImportance: BandRole::from($row['role'])->canPlanConcerts(),
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
