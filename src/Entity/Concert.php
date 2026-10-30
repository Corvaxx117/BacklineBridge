<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ConcertStatus;
use App\Repository\ConcertRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConcertRepository::class)]
#[ORM\Table(name: 'concert')]
class Concert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $revision = 1;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $date;

    #[ORM\Column(length: 150)]
    private string $venue;

    #[ORM\Column(length: 120)]
    private string $city;

    #[ORM\OneToOne(inversedBy: 'concert', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'patch_sheet_id', nullable: false)]
    private TechnicalSheet $patchSheet;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Band $band;

    #[ORM\Column(length: 20, enumType: ConcertStatus::class, options: ['default' => 'pending'])]
    private ConcertStatus $status = ConcertStatus::Pending;

    #[ORM\Column(length: 2000, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $createdByName = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    public function getStatus(): ConcertStatus
    {
        return $this->status;
    }

    public function confirm(): void
    {
        $this->status = ConcertStatus::Validated;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function updateDetails(
        \DateTimeImmutable $date,
        string $venue,
        string $city,
        ConcertStatus $status,
        ?string $notes = null,
    ): void {
        $this->date = $date;
        $this->venue = $venue;
        $this->city = $city;
        $this->status = $status;
        $this->notes = null !== $notes && '' !== trim($notes) ? trim($notes) : null;
    }

    public function __construct(
        \DateTimeImmutable $date,
        string $venue,
        string $city,
        TechnicalSheet $patchSheet,
        Band $band,
        ?User $createdBy = null,
    ) {
        $this->date = $date;
        $this->venue = $venue;
        $this->city = $city;
        $this->patchSheet = $patchSheet;
        $this->band = $band;
        $this->createdBy = $createdBy;
        $this->createdByName = $createdBy?->getDisplayName();
        $this->createdAt = new \DateTimeImmutable();
        $patchSheet->setConcert($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getCreatedByName(): ?string
    {
        return $this->createdByName;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getBand(): Band
    {
        return $this->band;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getVenue(): string
    {
        return $this->venue;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getPatchSheet(): TechnicalSheet
    {
        return $this->patchSheet;
    }
}
