<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PushSubscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PushSubscription> */
final class PushSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushSubscription::class);
    }

    public function device(User $user, string $token): ?PushSubscription
    {
        if (1 !== preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return null;
        }

        return $this->findOneBy(['user' => $user, 'deviceHash' => hash('sha256', $token)]);
    }

    /** @return list<PushSubscription> */
    public function forUser(User $user, string $vapidPublicKey): array
    {
        return $this->findBy(['user' => $user, 'vapidPublicKey' => $vapidPublicKey]);
    }
}
