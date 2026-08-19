<?php

namespace App\Modules\Destination\Repository;

use App\Modules\Destination\Entity\City;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<City>
 */
class CityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, City::class);
    }

    /**
     * @return City[]
     */
    public function findForAdminList(): array
    {
        return $this->createQueryBuilder('city')
            ->addSelect('country')
            ->innerJoin('city.country', 'country')
            ->orderBy('country.name', 'ASC')
            ->addOrderBy('city.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
