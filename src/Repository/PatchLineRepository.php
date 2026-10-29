<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PatchLine;
use App\Entity\TechnicalSheet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PatchLine> */
class PatchLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PatchLine::class);
    }

    /** @return list<PatchLine> */
    public function findForSheet(TechnicalSheet $sheet): array
    {
        return $this->findBy(['sheet' => $sheet, 'active' => true], ['channelNumber' => 'ASC']);
    }

    /** @return list<PatchLine> */
    public function findArchivedForSheet(TechnicalSheet $sheet): array
    {
        return $this->findBy(['sheet' => $sheet, 'active' => null], ['deletedAt' => 'DESC']);
    }
}
