<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TaskStatus;
use App\Repository\BandTaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BandTaskRepository::class)]
#[ORM\Table(name: 'band_task')]
#[ORM\Index(name: 'idx_task_board', columns: ['band_id', 'status', 'due_at'])]
class BandTask
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Version, ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $revision = 1;

    #[ORM\Column(length: 180)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?MemberProfile $assignee = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueAt = null;

    #[ORM\Column(length: 20, enumType: TaskStatus::class)]
    private TaskStatus $status = TaskStatus::Todo;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(length: 100)]
    private string $createdByName;

    public function __construct(
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)] private Band $band,
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $createdBy,
    ) {
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
        $this->createdByName = $createdBy?->getDisplayName() ?? 'Membre du groupe';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBand(): Band
    {
        return $this->band;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getAssignee(): ?MemberProfile
    {
        return $this->assignee;
    }

    public function getDueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getCreatedByName(): string
    {
        return $this->createdByName;
    }

    public function isOverdue(\DateTimeImmutable $today): bool
    {
        return TaskStatus::Done !== $this->status
            && null !== $this->dueAt
            && $this->dueAt->format('Y-m-d') < $today->format('Y-m-d');
    }

    public function update(
        string $title,
        ?string $description,
        ?MemberProfile $assignee,
        ?\DateTimeImmutable $dueAt,
        TaskStatus $status,
    ): void {
        if (null !== $assignee && $assignee->getBand()->getId() !== $this->band->getId()) {
            throw new \DomainException('Le responsable doit appartenir au groupe.');
        }
        $this->title = trim($title);
        $this->description = null !== $description && '' !== trim($description) ? trim($description) : null;
        $this->assignee = $assignee;
        $this->dueAt = $dueAt;
        $this->changeStatus($status);
    }

    public function changeStatus(TaskStatus $status): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        if ($status !== $this->status) {
            $this->completedAt = TaskStatus::Done === $status ? $this->updatedAt : null;
        }
        $this->status = $status;
    }
}
