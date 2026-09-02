<?php

namespace App\Modules\Activity\Repository;

use App\Modules\Activity\Entity\Activity;
use App\Modules\Destination\Entity\City;
use App\Shared\Admin\Pagination\PaginatedResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activity>
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    /**
     * @param array{q: string, city: int|null, category: string, active: string, featured: string, publicVisible: string, page: int, pageSize: int} $filters
     *
     * @return PaginatedResult<Activity>
     */
    public function findForAdminPage(array $filters): PaginatedResult
    {
        $builder = $this->baseListBuilder()
            ->orderBy('activity.id', 'DESC');

        if ($filters['q'] !== '') {
            $builder
                ->andWhere('activity.name LIKE :query OR activity.nameFa LIKE :query OR activity.slug LIKE :code OR city.name LIKE :query OR city.nameFa LIKE :query')
                ->setParameter('query', '%' . $filters['q'] . '%')
                ->setParameter('code', strtolower($filters['q']) . '%');
        }

        if ($filters['city'] !== null) {
            $builder->andWhere('city.id = :city')->setParameter('city', $filters['city']);
        }

        if ($filters['category'] !== '') {
            $builder->andWhere('activity.category = :category')->setParameter('category', $filters['category']);
        }

        $this->applyBooleanFilter($builder, 'activity.active', 'active', $filters['active']);
        $this->applyBooleanFilter($builder, 'activity.featured', 'featured', $filters['featured']);
        $this->applyBooleanFilter($builder, 'activity.publicVisible', 'publicVisible', $filters['publicVisible']);

        return $this->paginate($builder, $filters['page'], $filters['pageSize'], $filters);
    }

    /**
     * @return Activity[]
     */
    public function findActivePublicVisible(?City $city = null): array
    {
        $builder = $this->baseListBuilder()
            ->andWhere('activity.active = :active')
            ->andWhere('activity.publicVisible = :publicVisible')
            ->setParameter('active', true)
            ->setParameter('publicVisible', true)
            ->orderBy('activity.featured', 'DESC')
            ->addOrderBy('activity.id', 'DESC');

        if ($city instanceof City) {
            $builder->andWhere('activity.city = :city')->setParameter('city', $city);
        }

        return $builder->getQuery()->getResult();
    }

    private function baseListBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('activity')
            ->leftJoin('activity.city', 'city')
            ->leftJoin('activity.images', 'image')
            ->addSelect('city', 'image');
    }

    private function applyBooleanFilter(QueryBuilder $builder, string $field, string $parameter, string $value): void
    {
        if ($value === '1' || $value === '0') {
            $builder->andWhere($field . ' = :' . $parameter)->setParameter($parameter, $value === '1');
        }
    }

    /**
     * @param array<string, scalar|null> $filters
     *
     * @return PaginatedResult<Activity>
     */
    private function paginate(QueryBuilder $builder, int $page, int $pageSize, array $filters): PaginatedResult
    {
        $page = max(1, $page);
        $total = \count(new Paginator(clone $builder, true));
        $page = min($page, max(1, (int) ceil($total / $pageSize)));
        $paginator = new Paginator((clone $builder)->setFirstResult(($page - 1) * $pageSize)->setMaxResults($pageSize), true);

        return new PaginatedResult(array_values(iterator_to_array($paginator->getIterator())), $total, $page, $pageSize, $filters);
    }
}
