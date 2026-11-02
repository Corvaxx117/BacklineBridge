<?php

declare(strict_types=1);

namespace App\Service\Concert\Import\Bandsintown;

use App\Entity\Band;
use App\Entity\Concert;
use App\Integration\Bandsintown\Dto\BandsintownEvent;
use App\Repository\BandsintownConcertRepository;
use App\Repository\ConcertRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ImportPreview
{
    public function __construct(
        private BandsintownConcertRepository $sources,
        private ConcertRepository $concerts,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @param list<BandsintownEvent> $events
     * @return array<string, array{event: BandsintownEvent, state: string, concert: ?Concert}> */
    public function rows(Band $band, array $events): array
    {
        $linked = [];
        $occupied = [];
        foreach ($this->sources->identitiesForBand($band) as $source) {
            $linked[$source['artist_id'].':'.$source['event_id']] = $source['concert_id'];
            $occupied[$source['concert_id']] = true;
        }
        $existing = [];
        foreach ($this->concerts->identitiesForBand($band) as $concert) {
            $key = ConcertIdentity::key(
                $concert['date'],
                $concert['venue'],
                $concert['city'],
            );
            $existing[$key][] = $concert['id'];
        }
        $rows = [];
        foreach ($events as $event) {
            $concert = $linked[$event->artistId.':'.$event->id] ?? null;
            $state = null !== $concert ? 'imported' : 'new';
            if (null === $concert) {
                $matches =
                    $existing[ConcertIdentity::key($event->date, $event->venue, $event->city)] ??
                    [];
                if (count($matches) > 1) {
                    $state = 'ambiguous';
                } elseif ([] !== $matches) {
                    $concert = $matches[0];
                    $state = isset($occupied[$concert]) ? 'occupied' : 'link';
                }
            }
            $entity = null === $concert ? null : $this->entityManager->getReference(Concert::class, $concert);
            $rows[$event->id] = ['event' => $event, 'state' => $state, 'concert' => $entity];
        }

        return $rows;
    }
}
