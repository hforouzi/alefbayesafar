<?php

namespace App\Modules\Hotel\Repository;

use App\Modules\Hotel\Entity\HotelImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HotelImage>
 */
class HotelImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HotelImage::class);
    }
}
