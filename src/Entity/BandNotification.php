<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NotificationType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'band_notification')]
#[ORM\Index(name: 'idx_band_notification_created', columns: ['band_id', 'created_at', 'id'])]
class BandNotification
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $actorName;

    public function __construct(
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Band $band,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $actor,
        #[ORM\Column(length: 30, enumType: NotificationType::class)]
        private NotificationType $type,
        #[ORM\Column(length: 300)]
        private string $subjectLabel,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Concert $concert = null,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?BandDocument $document = null,
        #[ORM\Column(length: 200, nullable: true)]
        private ?string $contextLabel = null,
        #[ORM\Column(options: ['default' => 1])]
        private int $itemCount = 1,
    ) {
        $this->actorName = $actor?->getDisplayName() ?? 'Membre du groupe';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBand(): Band
    {
        return $this->band;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function getActorName(): string
    {
        return $this->actorName;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function getSubjectLabel(): string
    {
        return $this->subjectLabel;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getConcert(): ?Concert
    {
        return $this->concert;
    }

    public function getDocument(): ?BandDocument
    {
        return $this->document;
    }

    public function getContextLabel(): ?string
    {
        return $this->contextLabel;
    }

    public function getItemCount(): int
    {
        return $this->itemCount;
    }
}
