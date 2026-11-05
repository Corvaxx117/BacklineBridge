<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PushDeliveryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PushDeliveryRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_push_delivery', columns: ['recipient_id', 'subscription_id'])]
#[ORM\Index(name: 'idx_push_pending', columns: ['state', 'next_attempt_at', 'id'])]
class PushDelivery
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 12)]
    private string $state = 'pending';

    #[ORM\Column]
    private int $attempts = 0;

    #[ORM\Column(type: Types::DATETIME_UTC_IMMUTABLE)]
    private \DateTimeImmutable $nextAttemptAt;

    #[ORM\Column(type: Types::DATETIME_UTC_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastStatus = null;

    public function __construct(
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private PushSubscription $subscription,
        #[ORM\Column(type: Types::DATETIME_UTC_IMMUTABLE)] private \DateTimeImmutable $createdAt,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        private ?NotificationRecipient $recipient = null,
    ) {
        $this->nextAttemptAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubscription(): PushSubscription
    {
        return $this->subscription;
    }

    public function getRecipient(): ?NotificationRecipient
    {
        return $this->recipient;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getNextAttemptAt(): \DateTimeImmutable
    {
        return $this->nextAttemptAt;
    }

    public function finish(string $state, \DateTimeImmutable $at, ?int $status = null): void
    {
        $this->state = $state;
        $this->finishedAt = $at;
        $this->lastStatus = $status;
    }

    public function attempted(): void
    {
        ++$this->attempts;
    }

    public function retryAt(\DateTimeImmutable $at, ?int $status): void
    {
        $this->nextAttemptAt = $at;
        $this->lastStatus = $status;
    }
}
