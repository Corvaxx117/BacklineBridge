<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Band;
use App\Entity\MerchProduct;
use App\Entity\StockMovement;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MerchRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @return list<MerchProduct> */
    public function products(Band $band): array
    {
        return $this->em
            ->createQueryBuilder()
            ->select('product', 'variant')
            ->from(MerchProduct::class, 'product')
            ->leftJoin('product.variants', 'variant')
            ->where('product.band = :band')
            ->setParameter('band', $band)
            ->orderBy('product.category', 'ASC')
            ->addOrderBy('product.id', 'ASC')
            ->addOrderBy('variant.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<StockMovement> */
    public function history(Band $band): array
    {
        return $this->em
            ->createQueryBuilder()
            ->select('movement', 'variant', 'product', 'author')
            ->from(StockMovement::class, 'movement')
            ->join('movement.variant', 'variant')
            ->join('variant.product', 'product')
            ->leftJoin('movement.createdBy', 'author')
            ->where('product.band = :band')
            ->setParameter('band', $band)
            ->orderBy('movement.id', 'DESC')
            ->setMaxResults(30)
            ->getQuery()
            ->getResult();
    }
}
