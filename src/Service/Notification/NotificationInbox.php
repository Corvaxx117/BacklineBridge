<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\NotificationRecipient;
use App\Entity\User;
use App\Repository\NotificationRecipientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class NotificationInbox
{
    public function __construct(
        private NotificationRecipientRepository $recipients,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function setRead(User $user, int $id, bool $read): NotificationRecipient
    {
        $recipient = $this->recipients->visibleFor($user, $id)
            ?? throw new NotFoundHttpException('Cette notification est indisponible.');
        if ($read) {
            $recipient->markRead($this->clock->now()->setTimezone(new \DateTimeZone('UTC')));
        } else {
            $recipient->markUnread();
        }
        $this->entityManager->flush();

        return $recipient;
    }

    public function markAllRead(User $user): void
    {
        $this->recipients->markAllRead($user, $this->clock->now()->setTimezone(new \DateTimeZone('UTC')));
    }
}
