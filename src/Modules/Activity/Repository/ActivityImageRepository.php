<?php

namespace App\Modules\Activity\Repository;

use App\Modules\Activity\Entity\ActivityImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityImage>
 */
class ActivityImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityImage::class);
    }
}
