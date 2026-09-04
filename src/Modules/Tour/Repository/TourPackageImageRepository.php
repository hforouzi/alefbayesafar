<?php

namespace App\Modules\Tour\Repository;

use App\Modules\Tour\Entity\TourPackageImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TourPackageImage>
 */
class TourPackageImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TourPackageImage::class);
    }
}
