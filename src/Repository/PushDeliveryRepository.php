<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PushDelivery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PushDelivery> */
final class PushDeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushDelivery::class);
    }

    public function next(\DateTimeImmutable $now): ?PushDelivery
    {
        return $this->createQueryBuilder('delivery')
            ->addSelect('subscription', 'user', 'receipt', 'notice')
            ->join('delivery.subscription', 'subscription')
            ->join('subscription.user', 'user')
            ->leftJoin('delivery.recipient', 'receipt')
            ->leftJoin('receipt.notification', 'notice')
            ->where('delivery.state = :state AND delivery.nextAttemptAt <= :now')
            ->setParameter('state', 'pending')->setParameter('now', $now)
            ->orderBy('delivery.nextAttemptAt', 'ASC')->addOrderBy('delivery.id', 'ASC')
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    public function prune(\DateTimeImmutable $before): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM push_delivery WHERE finished_at < :before AND state <> :pending',
            ['before' => $before->format('Y-m-d H:i:s'), 'pending' => 'pending'],
        );
    }
}
