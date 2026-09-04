<?php

namespace App\Modules\Transfer\Repository;

use App\Modules\Transfer\Entity\TransferOffer;
use App\Modules\Transfer\Entity\TransferProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TransferOffer>
 */
class TransferOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TransferOffer::class);
    }

    /**
     * @return TransferOffer[]
     */
    public function findForProduct(TransferProduct $product): array
    {
        return $this->createQueryBuilder('offer')
            ->andWhere('offer.transferProduct = :product')
            ->setParameter('product', $product)
            ->orderBy('offer.active', 'DESC')
            ->addOrderBy('offer.priority', 'DESC')
            ->addOrderBy('offer.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TransferOffer[]
     */
    public function findActiveForProducts(array $products): array
    {
        if ($products === []) {
            return [];
        }

        return $this->createQueryBuilder('offer')
            ->andWhere('offer.transferProduct IN (:products)')
            ->andWhere('offer.active = true')
            ->setParameter('products', $products)
            ->getQuery()
            ->getResult();
    }
}
