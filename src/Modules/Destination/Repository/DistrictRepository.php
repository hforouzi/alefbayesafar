<?php

namespace App\Modules\Destination\Repository;

use App\Modules\Destination\Entity\District;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<District>
 */
class DistrictRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, District::class);
    }

    /**
     * @return District[]
     */
    public function findForAdminList(): array
    {
        return $this->createQueryBuilder('district')
            ->addSelect('city', 'country')
            ->innerJoin('district.city', 'city')
            ->innerJoin('city.country', 'country')
            ->orderBy('country.name', 'ASC')
            ->addOrderBy('city.name', 'ASC')
            ->addOrderBy('district.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
