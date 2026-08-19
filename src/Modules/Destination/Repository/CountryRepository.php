<?php

namespace App\Modules\Destination\Repository;

use App\Modules\Destination\Entity\Country;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Country>
 */
class CountryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Country::class);
    }

    /**
     * @return Country[]
     */
    public function findForAdminList(): array
    {
        return $this->createQueryBuilder('country')
            ->orderBy('country.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
