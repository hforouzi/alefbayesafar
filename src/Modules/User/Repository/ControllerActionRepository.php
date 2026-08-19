<?php

namespace App\Modules\User\Repository;

use App\Modules\User\Entity\ControllerAction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ControllerAction>
 *
 * @method ControllerAction|null find($id, $lockMode = null, $lockVersion = null)
 * @method ControllerAction|null findOneBy(array $criteria, array $orderBy = null)
 * @method ControllerAction[]    findAll()
 * @method ControllerAction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ControllerActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ControllerAction::class);
    }

//    /**
//     * @return Role[] Returns an array of Role objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Role
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

}
