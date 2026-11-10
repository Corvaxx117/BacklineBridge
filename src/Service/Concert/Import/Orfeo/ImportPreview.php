<?php

declare(strict_types=1);

namespace App\Service\Concert\Import\Orfeo;

use App\Entity\Band;
use App\Enum\ConcertStatus;
use App\Integration\Orfeo\Dto\OrfeoEvent;
use App\Repository\OrfeoConcertRepository;
use App\Service\Concert\Import\Bandsintown\ConcertIdentity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ImportPreview
{
    public function __construct(private EntityManagerInterface $em, private OrfeoConcertRepository $sources)
    {
    }

    /** @return array<int, array{id: int, date: string, venue: string, city: string, revision: int, startTime: ?string, status: ConcertStatus}> */
    public function concerts(Band $band): array
    {
        $rows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT id, date, venue, city, revision, start_time, status FROM concert WHERE band_id = ? ORDER BY date DESC, id DESC',
            [$band->getId()],
        );
        $result = [];
        foreach ($rows as $row) {
            $row['id'] = (int) $row['id'];
            $row['revision'] = (int) $row['revision'];
            $result[$row['id']] = [
                'id' => $row['id'],
                'revision' => $row['revision'],
                'date' => (string) $row['date'],
                'venue' => (string) $row['venue'],
                'city' => (string) $row['city'],
                'startTime' => $row['start_time'],
                'status' => ConcertStatus::from($row['status']),
            ];
        }

        return $result;
    }

    /** @param list<OrfeoEvent> $events
     * @return array<string, array{event: OrfeoEvent, state: string, concert: ?array{id: int, date: string, venue: string, city: string, revision: int, startTime: ?string, status: ConcertStatus}, target: string}> */
    public function rows(Band $band, array $events): array
    {
        $existing = $this->concerts($band);
        $linked = $occupied = [];
        foreach ($this->sources->identitiesForBand($band) as $row) {
            $linked[$row['project_id'].':'.$row['performance_id']] = $row['concert_id'];
            $occupied[$row['concert_id']] = true;
        }
        $rows = [];
        foreach ($events as $event) {
            $id = $linked[$event->id] ?? null;
            $state = null !== $id ? 'imported' : 'new';
            $target = null !== $id ? (string) $id : 'new';
            if (null === $id) {
                $key = ConcertIdentity::key($event->date, $event->venue, $event->city);
                $matches = array_filter($existing, static fn (array $row): bool => ConcertIdentity::key($row['date'], $row['venue'], $row['city']) === $key);
                $possible = array_filter($existing, static fn (array $row): bool => $row['date'] === $event->date);
                if (1 === count($matches)) {
                    $id = (int) array_key_first($matches);
                    $target = (string) $id;
                    $state = isset($occupied[$id]) ? 'occupied' : 'link';
                } elseif ([] !== $possible) {
                    $state = 'choose';
                    $target = '';
                }
            }
            if ($event->cancelled) {
                $state = 'cancelled';
            }
            $rows[$event->id] = ['event' => $event, 'state' => $state, 'target' => $target,
                'concert' => null === $id ? null : ($existing[$id] ?? null)];
        }

        return $rows;
    }
}
