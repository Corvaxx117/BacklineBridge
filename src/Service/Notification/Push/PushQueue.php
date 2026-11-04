<?php

declare(strict_types=1);

namespace App\Service\Notification\Push;

use App\Entity\NotificationRecipient;
use App\Entity\PushDelivery;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Les envois sont persistés dans la même transaction que le concert ou le document. */
final readonly class PushQueue
{
    public function __construct(
        private EntityManagerInterface $em,
        private PushSubscriptionRepository $subscriptions,
        private PushConfiguration $configuration,
    ) {
    }

    public function record(NotificationRecipient $recipient): void
    {
        if (null !== $recipient->getReadAt() || !$this->configuration->available()) {
            return;
        }
        foreach ($this->subscriptions->forUser($recipient->getUser(), $this->configuration->publicKey()) as $device) {
            $this->em->persist(new PushDelivery($device, $recipient->getNotification()->getCreatedAt(), $recipient));
        }
    }
}
