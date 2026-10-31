<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Band;
use App\Entity\BandTask;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BandTask> */
final class BandTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BandTask::class);
    }

    /** @return list<BandTask> */
    public function forBoard(Band $band, User $user, string $scope, string $search): array
    {
        $query = $this->createQueryBuilder('task')
            ->addSelect('assignee')
            ->leftJoin('task.assignee', 'assignee')
            ->andWhere('task.band = :band')
            ->setParameter('band', $band)
            ->addSelect('CASE WHEN task.dueAt IS NULL THEN 1 ELSE 0 END AS HIDDEN undated')
            ->orderBy('undated', 'ASC')
            ->addOrderBy('task.dueAt', 'ASC')
            ->addOrderBy('task.createdAt', 'DESC')
            ->addOrderBy('task.id', 'DESC');
        if ('mine' === $scope) {
            $query->andWhere('assignee.user = :user')->setParameter('user', $user);
        } elseif ('unassigned' === $scope) {
            $query->andWhere('task.assignee IS NULL');
        }
        if ('' !== $search) {
            // Escape LIKE wildcards so a search remains a literal fragment.
            $pattern = '%'.strtr(mb_strtolower($search), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $query->andWhere($query->expr()->orX(
                "LOWER(task.title) LIKE :search ESCAPE '!'",
                "LOWER(task.description) LIKE :search ESCAPE '!'",
            ))
                ->setParameter('search', $pattern);
        }

        return $query->getQuery()->getResult();
    }
}
