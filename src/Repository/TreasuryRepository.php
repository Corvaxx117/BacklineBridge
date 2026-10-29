<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\{Band, TreasuryAccount, TreasuryEntry, FinanceNote};
use App\Enum\TreasuryAccountKind;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TreasuryRepository
{
    public function __construct(private EntityManagerInterface $em) {}
    /** @return list<TreasuryAccount> */
    public function accounts(Band $band): array
    {
        return $this->em
            ->getRepository(TreasuryAccount::class)
            ->findBy(['band' => $band], ['kind' => 'ASC']);
    }

    public function account(Band $band, TreasuryAccountKind $kind): TreasuryAccount
    {
        return $this->em
            ->getRepository(TreasuryAccount::class)
            ->findOneBy(['band' => $band, 'kind' => $kind]) ??
            throw new \DomainException('La trésorerie du groupe n’est pas encore initialisée.');
    }
    /** @return list<TreasuryEntry> */
    public function entries(Band $band, int $page = 1): array
    {
        return $this->em
            ->createQueryBuilder()
            ->select('entry', 'account', 'author')
            ->from(TreasuryEntry::class, 'entry')
            ->join('entry.account', 'account')
            ->leftJoin('entry.createdBy', 'author')
            ->where('account.band = :band')
            ->setParameter('band', $band)
            ->orderBy('entry.occurredOn', 'DESC')
            ->addOrderBy('entry.id', 'DESC')
            ->setFirstResult(($page - 1) * 25)
            ->setMaxResults(25)
            ->getQuery()
            ->getResult();
    }

    public function countEntries(Band $band): int
    {
        return (int) $this->em
            ->createQueryBuilder()
            ->select('COUNT(entry.id)')
            ->from(TreasuryEntry::class, 'entry')
            ->join('entry.account', 'account')
            ->where('account.band = :band')
            ->setParameter('band', $band)
            ->getQuery()
            ->getSingleScalarResult();
    }
    /** @return list<TreasuryEntry> */
    public function chartEntries(Band $band, \DateTimeImmutable $since): array
    {
        return $this->em
            ->createQueryBuilder()
            ->select('entry')
            ->from(TreasuryEntry::class, 'entry')
            ->join('entry.account', 'account')
            ->where('account.band = :band')
            ->andWhere('entry.occurredOn >= :since')
            ->andWhere('entry.cancelledAt IS NULL')
            ->andWhere('entry.kind IN (:kinds)')
            ->setParameter('band', $band)
            ->setParameter('since', $since)
            ->setParameter('kinds', ['income', 'expense'])
            ->getQuery()
            ->getResult();
    }
    /** @return list<FinanceNote> */
    public function notes(Band $band): array
    {
        return $this->em
            ->getRepository(FinanceNote::class)
            ->findBy(['band' => $band], ['resolvedAt' => 'ASC', 'id' => 'DESC']);
    }
}
