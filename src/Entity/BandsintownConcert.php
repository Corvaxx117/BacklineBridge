<?php

declare(strict_types=1);

namespace App\Entity;

use App\Integration\Bandsintown\Dto\BandsintownEvent;
use App\Repository\BandsintownConcertRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BandsintownConcertRepository::class)]
#[ORM\Table(name: 'bandsintown_concert')]
#[
    ORM\UniqueConstraint(
        name: 'uniq_bit_band_artist_event',
        columns: ['band_id', 'artist_id', 'event_id'],
    ),
]
class BandsintownConcert
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    private Band $band;
    #[ORM\Column(length: 30)]
    private string $artistId;
    #[ORM\Column(length: 30)]
    private string $eventId;
    #[ORM\Column(type: Types::JSON)]
    private array $snapshot;
    #[ORM\Column]
    private \DateTimeImmutable $importedAt;

    public function __construct(
        #[ORM\OneToOne, ORM\JoinColumn(nullable: false)] private Concert $concert,
        BandsintownEvent $event,
    ) {
        $this->band = $concert->getBand();
        $this->artistId = $event->artistId;
        $this->eventId = $event->id;
        $this->snapshot = $event->toArray();
        $this->importedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getConcert(): Concert
    {
        return $this->concert;
    }
    public function getImportedAt(): \DateTimeImmutable
    {
        return $this->importedAt;
    }
    public function getEvent(): BandsintownEvent
    {
        return BandsintownEvent::fromArray($this->snapshot);
    }
    public function getArtistId(): string
    {
        return $this->artistId;
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }
}
