<?php

namespace App\Modules\Destination\Repository;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\District;
use App\Modules\Destination\ValueObject\PaginatedResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
            ->addSelect('city', 'state', 'country')
            ->innerJoin('district.city', 'city')
            ->leftJoin('city.state', 'state')
            ->innerJoin('city.country', 'country')
            ->orderBy('country.name', 'ASC')
            ->addOrderBy('state.name', 'ASC')
            ->addOrderBy('city.name', 'ASC')
            ->addOrderBy('district.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array{q: string, name: string, nameFa: string, country: int|null, city: int|null, active: string, page: int, pageSize: int} $filters
     *
     * @return PaginatedResult<District>
     */
    public function findForAdminPage(array $filters): PaginatedResult
    {
        $builder = $this->createQueryBuilder('district')
            ->addSelect('city', 'state', 'country')
            ->innerJoin('district.city', 'city')
            ->leftJoin('city.state', 'state')
            ->innerJoin('city.country', 'country')
            ->orderBy('country.name', 'ASC')
            ->addOrderBy('state.name', 'ASC')
            ->addOrderBy('city.name', 'ASC')
            ->addOrderBy('district.name', 'ASC');

        if ($filters['q'] !== '') {
            $builder
                ->andWhere('district.name LIKE :globalQuery OR district.nameFa LIKE :globalQuery OR city.name LIKE :globalQuery OR city.nameFa LIKE :globalQuery OR state.name LIKE :globalQuery OR state.nameFa LIKE :globalQuery OR country.name LIKE :globalQuery OR country.nameFa LIKE :globalQuery OR country.iso2 LIKE :globalCode')
                ->setParameter('globalQuery', '%' . $filters['q'] . '%')
                ->setParameter('globalCode', strtoupper($filters['q']) . '%');
        }
        $this->applyTextFilter($builder, 'district.name', 'name', $filters['name']);
        $this->applyTextFilter($builder, 'district.nameFa', 'nameFa', $filters['nameFa']);
        if ($filters['country'] !== null) {
            $builder->andWhere('country.id = :countryId')->setParameter('countryId', $filters['country']);
        }
        if ($filters['city'] !== null) {
            $builder->andWhere('city.id = :cityId')->setParameter('cityId', $filters['city']);
        }

        $this->applyActiveFilter($builder, $filters['active'], 'district');

        return $this->paginate($builder, $filters['page'], $filters['pageSize'], $filters);
    }

    /**
     * @return District[]
     */
    public function search(string $query, ?City $city = null, int $limit = 25): array
    {
        $builder = $this->createQueryBuilder('district')
            ->addSelect('city', 'country')
            ->innerJoin('district.city', 'city')
            ->innerJoin('city.country', 'country')
            ->andWhere('district.name LIKE :query OR district.nameFa LIKE :query')
            ->setParameter('query', '%' . trim($query) . '%')
            ->orderBy('country.name', 'ASC')
            ->addOrderBy('city.name', 'ASC')
            ->addOrderBy('district.name', 'ASC')
            ->setMaxResults($limit);

        if ($city instanceof City) {
            $builder->andWhere('district.city = :city')->setParameter('city', $city);
        }

        return $builder->getQuery()->getResult();
    }

    private function applyActiveFilter(QueryBuilder $builder, string $active, string $alias): void
    {
        if ($active === '1' || $active === '0') {
            $builder
                ->andWhere($alias . '.active = :active')
                ->setParameter('active', $active === '1');
        }
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
     * @return PaginatedResult<District>
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
