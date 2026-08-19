<?php

namespace App\Modules\Default\Repository;

use App\Modules\Default\Entity\MenuCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuCategory>
 */
class MenuCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuCategory::class);
    }

    /**
     * @return MenuCategory[]
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('mc')
            ->andWhere('mc.active = :active')
            ->setParameter('active', true)
            ->orderBy('mc.position', 'ASC')
            ->addOrderBy('mc.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByCode(string $code): ?MenuCategory
    {
        return $this->findOneBy(['code' => mb_strtolower(trim($code))]);
    }
}
