<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Band;
use App\Entity\BandDocument;
use App\Entity\Concert;
use App\Enum\DocumentCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BandDocument> */
final class BandDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BandDocument::class);
    }

    /** @return list<BandDocument> */
    public function forBand(Band $band, string $search = '', ?DocumentCategory $category = null): array
    {
        $query = $this->createQueryBuilder('document')
            ->addSelect('concert')
            ->leftJoin('document.concert', 'concert')
            ->where('document.band = :band')
            ->setParameter('band', $band)
            ->orderBy('document.createdAt', 'DESC')
            ->addOrderBy('document.id', 'DESC');

        if ('' !== $search) {
            $query->andWhere('(LOWER(document.title) LIKE :search OR LOWER(document.description) LIKE :search)')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }
        if (null !== $category) {
            $query->andWhere('document.category = :category')->setParameter('category', $category);
        }

        return $query->getQuery()->getResult();
    }

    /** @return list<BandDocument> */
    public function forConcert(Concert $concert): array
    {
        return $this->findBy(['concert' => $concert], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }
}
