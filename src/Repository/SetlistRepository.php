<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Setlist;
use App\Entity\TechnicalSheet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Setlist> */
final class SetlistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setlist::class);
    }

    public function forSheet(TechnicalSheet $sheet): ?Setlist
    {
        return $this->findOneBy(['sheet' => $sheet]);
    }
}
