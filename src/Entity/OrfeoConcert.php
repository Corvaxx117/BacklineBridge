<?php

declare(strict_types=1);

namespace App\Entity;

use App\Integration\Orfeo\Dto\OrfeoEvent;
use App\Repository\OrfeoConcertRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrfeoConcertRepository::class)]
#[ORM\Table(name: 'orfeo_concert')]
#[ORM\UniqueConstraint(name: 'uniq_orfeo_performance', columns: ['project_id', 'performance_id'])]
class OrfeoConcert
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Version, ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $revision = 1;

    #[ORM\Column]
    private int $projectId;

    #[ORM\Column]
    private int $performanceId;

    #[ORM\Column]
    private int $spectacleId;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $snapshot;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $contactLinks = [];

    #[ORM\Column(type: Types::DATETIME_UTC_IMMUTABLE)]
    private \DateTimeImmutable $syncedAt;

    public function __construct(
        #[ORM\OneToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Concert $concert,
        OrfeoEvent $event,
        \DateTimeImmutable $at,
    ) {
        $this->projectId = $event->projectId;
        $this->performanceId = $event->performanceId;
        $this->spectacleId = $event->spectacleId;
        $this->refresh($event, $at);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function getConcert(): Concert
    {
        return $this->concert;
    }

    public function getEvent(): OrfeoEvent
    {
        return OrfeoEvent::fromArray($this->snapshot);
    }

    public function getSyncedAt(): \DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function refresh(OrfeoEvent $event, \DateTimeImmutable $at): void
    {
        if ($event->projectId !== $this->projectId || $event->performanceId !== $this->performanceId
            || $event->spectacleId !== $this->spectacleId) {
            throw new \DomainException('La représentation Orfeo a changé d’identité. Recharge l’aperçu.');
        }
        $this->snapshot = $event->toArray();
        $this->syncedAt = $at->setTimezone(new \DateTimeZone('UTC'));
    }

    /** @return array<string, mixed> */
    public function getContactLinks(): array
    {
        return $this->contactLinks;
    }

    /** @param array<string, mixed> $links */
    public function setContactLinks(array $links): void
    {
        $this->contactLinks = $links;
    }
}
