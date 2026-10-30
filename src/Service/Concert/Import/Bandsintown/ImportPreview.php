<?php

declare(strict_types=1);

namespace App\Service\Concert\Import\Bandsintown;

use App\Entity\Band;
use App\Integration\Bandsintown\Dto\BandsintownEvent;
use App\Repository\BandsintownConcertRepository;
use App\Repository\ConcertRepository;

final readonly class ImportPreview
{
    public function __construct(
        private BandsintownConcertRepository $sources,
        private ConcertRepository $concerts,
    ) {
    }

    /** @param list<BandsintownEvent> $events
     * @return array<string, array{event: BandsintownEvent, state: string, concert: ?\App\Entity\Concert}> */
    public function rows(Band $band, array $events): array
    {
        $linked = [];
        $occupied = [];
        foreach ($this->sources->forBand($band) as $source) {
            $linked[$source->getArtistId().':'.$source->getEventId()] = $source->getConcert();
            $occupied[$source->getConcert()->getId()] = true;
        }
        $existing = [];
        foreach ($this->concerts->findBy(['band' => $band], ['id' => 'ASC']) as $concert) {
            $key = ConcertIdentity::key(
                $concert->getDate()->format('Y-m-d'),
                $concert->getVenue(),
                $concert->getCity(),
            );
            $existing[$key][] = $concert;
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
                    $state = isset($occupied[$concert->getId()]) ? 'occupied' : 'link';
                }
            }
            $rows[$event->id] = ['event' => $event, 'state' => $state, 'concert' => $concert];
        }

        return $rows;
    }
}
