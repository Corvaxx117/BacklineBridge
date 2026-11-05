<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationRecipientRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRecipientRepository::class)]
#[ORM\Table(name: 'notification_recipient')]
#[ORM\UniqueConstraint(name: 'uniq_notification_user', columns: ['notification_id', 'user_id'])]
#[ORM\Index(name: 'idx_notification_inbox', columns: ['user_id', 'read_at', 'notification_id'])]
class NotificationRecipient
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_UTC_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    public function __construct(
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private BandNotification $notification,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNotification(): BandNotification
    {
        return $this->notification;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function markRead(\DateTimeImmutable $at): void
    {
        $this->readAt ??= $at;
    }

    public function markUnread(): void
    {
        $this->readAt = null;
    }
}
