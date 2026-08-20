<?php

namespace App\Modules\Destination\Repository;

use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\ValueObject\PaginatedResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    /**
     * @param array{q: string, name: string, nameFa: string, iso2: string, iso3: string, active: string, page: int, pageSize: int} $filters
     *
     * @return PaginatedResult<Country>
     */
    public function findForAdminPage(array $filters): PaginatedResult
    {
        $builder = $this->createQueryBuilder('country')
            ->orderBy('country.name', 'ASC');

        if ($filters['q'] !== '') {
            $builder
                ->andWhere('country.name LIKE :globalQuery OR country.nameFa LIKE :globalQuery OR country.iso2 LIKE :globalCode OR country.iso3 LIKE :globalCode')
                ->setParameter('globalQuery', '%' . $filters['q'] . '%')
                ->setParameter('globalCode', strtoupper($filters['q']) . '%');
        }
        $this->applyTextFilter($builder, 'country.name', 'name', $filters['name']);
        $this->applyTextFilter($builder, 'country.nameFa', 'nameFa', $filters['nameFa']);
        if ($filters['iso2'] !== '') {
            $builder->andWhere('country.iso2 LIKE :iso2')->setParameter('iso2', $filters['iso2'] . '%');
        }
        if ($filters['iso3'] !== '') {
            $builder->andWhere('country.iso3 LIKE :iso3')->setParameter('iso3', $filters['iso3'] . '%');
        }

        $this->applyActiveFilter($builder, $filters['active'], 'country');

        return $this->paginate($builder, $filters['page'], $filters['pageSize'], $filters);
    }

    /**
     * @return Country[]
     */
    public function search(string $query, int $limit = 25): array
    {
        $query = trim($query);

        return $this->createQueryBuilder('country')
            ->andWhere('country.name LIKE :query OR country.nameFa LIKE :query OR country.iso2 LIKE :code OR country.iso3 LIKE :code')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('code', strtoupper($query) . '%')
            ->orderBy('country.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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
     * @return PaginatedResult<Country>
     */
    private function paginate(QueryBuilder $builder, int $page, int $pageSize, array $filters): PaginatedResult
    {
        $page = max(1, $page);
        $total = \count(new Paginator(clone $builder));
        $page = min($page, max(1, (int) ceil($total / $pageSize)));
        $paginator = new Paginator((clone $builder)->setFirstResult(($page - 1) * $pageSize)->setMaxResults($pageSize));

        return new PaginatedResult(
            array_values(iterator_to_array($paginator->getIterator())),
            $total,
            $page,
            $pageSize,
            $filters,
        );
    }
}
