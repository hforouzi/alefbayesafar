<?php

namespace App\Modules\Destination\Repository;

use App\Modules\Destination\Entity\Airport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Airport>
 */
class AirportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Airport::class);
    }

    /**
     * @return Airport[]
     */
    public function findForAdminList(): array
    {
        return $this->createQueryBuilder('airport')
            ->addSelect('city', 'country')
            ->innerJoin('airport.city', 'city')
            ->innerJoin('city.country', 'country')
            ->orderBy('country.name', 'ASC')
            ->addOrderBy('city.name', 'ASC')
            ->addOrderBy('airport.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
