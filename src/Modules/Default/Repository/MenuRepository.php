<?php

namespace App\Modules\Default\Repository;

use App\Modules\Default\Entity\Menu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Menu>
 */
class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
    }
    
    public function findByParent($parent = null)
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.parent = :parent')
            ->setParameter('parent', $parent)
            ->orderBy('m.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
