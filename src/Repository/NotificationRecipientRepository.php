<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BandMembership;
use App\Entity\NotificationRecipient;
use App\Entity\User;
use App\Service\Security\ReadRequestCache;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NotificationRecipient> */
final class NotificationRecipientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly ReadRequestCache $reads)
    {
        parent::__construct($registry, NotificationRecipient::class);
    }

    public function unreadCount(User $user): int
    {
        return $this->reads->remember(
            'notification_count.'.$user->getId(),
            fn (): int => $this->countVisible($user, true),
        );
    }

    public function countVisible(User $user, bool $unreadOnly = false): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            <<<'SQL'
                SELECT COUNT(*) FROM notification_recipient receipt
                JOIN band_notification notice ON notice.id = receipt.notification_id
                JOIN band_membership membership ON membership.band_id = notice.band_id
                    AND membership.user_id = receipt.user_id
                WHERE receipt.user_id = :user
                SQL.($unreadOnly ? ' AND receipt.read_at IS NULL' : ''),
            ['user' => $user->getId()],
        );
    }

    /** @return list<NotificationRecipient> */
    public function pageFor(User $user, int $page, bool $unreadOnly, int $limit): array
    {
        $query = $this->visibleQuery($user)
            ->addSelect('notice', 'band', 'concert', 'document')
            ->join('notice.band', 'band')
            ->leftJoin('notice.concert', 'concert')
            ->leftJoin('notice.document', 'document')
            ->orderBy('notice.createdAt', 'DESC')
            ->addOrderBy('notice.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        if ($unreadOnly) {
            $query->andWhere('receipt.readAt IS NULL');
        }

        return $query->getQuery()->getResult();
    }

    public function visibleFor(User $user, int $id): ?NotificationRecipient
    {
        return $this->visibleQuery($user)
            ->addSelect('notice')
            ->andWhere('receipt.id = :id')
            ->setParameter('id', $id)
            ->getQuery()->getOneOrNullResult();
    }

    public function markAllRead(User $user, \DateTimeImmutable $at): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            <<<'SQL'
                UPDATE notification_recipient SET read_at = :read_at
                WHERE user_id = :user AND read_at IS NULL
                    AND notification_id IN (
                        SELECT notice.id FROM band_notification notice
                        JOIN band_membership membership ON membership.band_id = notice.band_id
                            AND membership.user_id = :user
                    )
                SQL,
            ['user' => $user->getId(), 'read_at' => $at->format('Y-m-d H:i:s')],
        );
    }

    private function visibleQuery(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('receipt')
            ->join('receipt.notification', 'notice')
            ->join(
                BandMembership::class,
                'membership',
                'WITH',
                'membership.band = notice.band AND membership.user = :user',
            )
            ->where('receipt.user = :user')
            ->setParameter('user', $user);
    }
}
