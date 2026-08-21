<?php

namespace App\Modules\Hotel\Repository;

use App\Modules\Hotel\Entity\HotelAmenity;
use App\Shared\Admin\Pagination\PaginatedResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HotelAmenity>
 */
class HotelAmenityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HotelAmenity::class);
    }

    /**
     * @return HotelAmenity[]
     */
    public function findActiveForForm(): array
    {
        return $this->createQueryBuilder('amenity')
            ->andWhere('amenity.active = :active')
            ->setParameter('active', true)
            ->orderBy('amenity.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array{q: string, name: string, nameFa: string, code: string, active: string, page: int, pageSize: int} $filters
     *
     * @return PaginatedResult<HotelAmenity>
     */
    public function findForAdminPage(array $filters): PaginatedResult
    {
        $builder = $this->createQueryBuilder('amenity')
            ->orderBy('amenity.name', 'ASC');

        if ($filters['q'] !== '') {
            $builder
                ->andWhere('amenity.name LIKE :globalQuery OR amenity.nameFa LIKE :globalQuery OR amenity.code LIKE :globalCode')
                ->setParameter('globalQuery', '%' . $filters['q'] . '%')
                ->setParameter('globalCode', $filters['q'] . '%');
        }

        $this->applyTextFilter($builder, 'amenity.name', 'name', $filters['name']);
        $this->applyTextFilter($builder, 'amenity.nameFa', 'nameFa', $filters['nameFa']);

        if ($filters['code'] !== '') {
            $builder->andWhere('amenity.code LIKE :code')->setParameter('code', $filters['code'] . '%');
        }

        if ($filters['active'] === '1' || $filters['active'] === '0') {
            $builder
                ->andWhere('amenity.active = :active')
                ->setParameter('active', $filters['active'] === '1');
        }

        return $this->paginate($builder, $filters['page'], $filters['pageSize'], $filters);
    }

    private function applyTextFilter(QueryBuilder $builder, string $field, string $parameter, string $value): void
    {
        if ($value !== '') {
            $builder
                ->andWhere($field . ' LIKE :' . $parameter)
                ->setParameter($parameter, '%' . $value . '%');
        }
    }

    /**
     * @param array<string, scalar|null> $filters
     *
     * @return PaginatedResult<HotelAmenity>
     */
    private function paginate(QueryBuilder $builder, int $page, int $pageSize, array $filters): PaginatedResult
    {
        $page = max(1, $page);
        $total = \count(new Paginator(clone $builder));
        $page = min($page, max(1, (int) ceil($total / $pageSize)));
        $paginator = new Paginator((clone $builder)->setFirstResult(($page - 1) * $pageSize)->setMaxResults($pageSize));

        return new PaginatedResult(array_values(iterator_to_array($paginator->getIterator())), $total, $page, $pageSize, $filters);
    }
}
