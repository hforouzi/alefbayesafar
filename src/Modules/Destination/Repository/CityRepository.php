<?php

namespace App\Modules\Destination\Repository;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Entity\State;
use App\Modules\Destination\ValueObject\PaginatedResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
            ->addSelect('country', 'state')
            ->innerJoin('city.country', 'country')
            ->leftJoin('city.state', 'state')
            ->orderBy('country.name', 'ASC')
            ->addOrderBy('state.name', 'ASC')
            ->addOrderBy('city.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array{q: string, name: string, nameFa: string, country: int|null, state: int|null, active: string, page: int, pageSize: int} $filters
     *
     * @return PaginatedResult<City>
     */
    public function findForAdminPage(array $filters): PaginatedResult
    {
        $builder = $this->createQueryBuilder('city')
            ->addSelect('country', 'state')
            ->innerJoin('city.country', 'country')
            ->leftJoin('city.state', 'state')
            ->orderBy('country.name', 'ASC')
            ->addOrderBy('state.name', 'ASC')
            ->addOrderBy('city.name', 'ASC');

        if ($filters['q'] !== '') {
            $builder
                ->andWhere('city.name LIKE :globalQuery OR city.nameFa LIKE :globalQuery OR city.slug LIKE :globalQuery OR state.name LIKE :globalQuery OR state.nameFa LIKE :globalQuery OR country.name LIKE :globalQuery OR country.nameFa LIKE :globalQuery OR country.iso2 LIKE :globalCode')
                ->setParameter('globalQuery', '%' . $filters['q'] . '%')
                ->setParameter('globalCode', strtoupper($filters['q']) . '%');
        }
        $this->applyTextFilter($builder, 'city.name', 'name', $filters['name']);
        $this->applyTextFilter($builder, 'city.nameFa', 'nameFa', $filters['nameFa']);
        if ($filters['country'] !== null) {
            $builder->andWhere('country.id = :countryId')->setParameter('countryId', $filters['country']);
        }
        if ($filters['state'] !== null) {
            $builder->andWhere('state.id = :stateId')->setParameter('stateId', $filters['state']);
        }

        $this->applyActiveFilter($builder, $filters['active'], 'city');

        return $this->paginate($builder, $filters['page'], $filters['pageSize'], $filters);
    }

    /**
     * @return City[]
     */
    public function search(string $query, ?Country $country = null, ?State $state = null, int $limit = 25): array
    {
        $builder = $this->createQueryBuilder('city')
            ->addSelect('country', 'state')
            ->innerJoin('city.country', 'country')
            ->leftJoin('city.state', 'state')
            ->andWhere('city.name LIKE :query OR city.nameFa LIKE :query')
            ->setParameter('query', '%' . trim($query) . '%')
            ->orderBy('country.name', 'ASC')
            ->addOrderBy('state.name', 'ASC')
            ->addOrderBy('city.name', 'ASC')
            ->setMaxResults($limit);

        if ($country instanceof Country) {
            $builder->andWhere('city.country = :country')->setParameter('country', $country);
        }

        if ($state instanceof State) {
            $builder->andWhere('city.state = :state')->setParameter('state', $state);
        }

        return $builder->getQuery()->getResult();
    }

    /**
     * @return City[]
     */
    public function findActiveByCountry(Country $country, int $limit = 25): array
    {
        return $this->createQueryBuilder('city')
            ->addSelect('country', 'state')
            ->innerJoin('city.country', 'country')
            ->leftJoin('city.state', 'state')
            ->andWhere('city.country = :country')
            ->andWhere('city.active = :active')
            ->setParameter('country', $country)
            ->setParameter('active', true)
            ->orderBy('city.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Active cities in a country that have at least one active airport —
     * the last-resort candidate pool for country-only destination discovery
     * when no configured tour source already names supported cities.
     *
     * @return City[]
     */
    public function findActiveWithAirportForCountry(Country $country, int $limit = 25): array
    {
        return $this->createQueryBuilder('city')
            ->addSelect('country')
            ->innerJoin('city.country', 'country')
            ->innerJoin('city.airports', 'airport')
            ->andWhere('city.country = :country')
            ->andWhere('city.active = :active')
            ->andWhere('airport.active = :active')
            ->setParameter('country', $country)
            ->setParameter('active', true)
            ->groupBy('city.id')
            ->orderBy('city.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOneByCountryStateAndName(Country $country, ?State $state, string $name): ?City
    {
        $builder = $this->createQueryBuilder('city')
            ->andWhere('city.country = :country')
            ->andWhere('city.name = :name')
            ->setParameter('country', $country)
            ->setParameter('name', $name)
            ->setMaxResults(1);

        if ($state instanceof State) {
            $builder->andWhere('city.state = :state')->setParameter('state', $state);
        } else {
            $builder->andWhere('city.state IS NULL');
        }

        $result = $builder->getQuery()->getOneOrNullResult();

        return $result instanceof City ? $result : null;
    }

    public function findOneByCountryStateAndSlug(Country $country, ?State $state, string $slug): ?City
    {
        $builder = $this->createQueryBuilder('city')
            ->andWhere('city.country = :country')
            ->andWhere('city.slug = :slug')
            ->setParameter('country', $country)
            ->setParameter('slug', $slug)
            ->setMaxResults(1);

        if ($state instanceof State) {
            $builder->andWhere('city.state = :state')->setParameter('state', $state);
        } else {
            $builder->andWhere('city.state IS NULL');
        }

        $result = $builder->getQuery()->getOneOrNullResult();

        return $result instanceof City ? $result : null;
    }

    public function findOneUnambiguousByCountryAndName(Country $country, string $name): ?City
    {
        $results = $this->createQueryBuilder('city')
            ->andWhere('city.country = :country')
            ->andWhere('city.name = :name')
            ->setParameter('country', $country)
            ->setParameter('name', $name)
            ->setMaxResults(2)
            ->getQuery()
            ->getResult();

        return \count($results) === 1 && $results[0] instanceof City ? $results[0] : null;
    }

    public function findOneUnambiguousByCountryAndSlug(Country $country, string $slug): ?City
    {
        $results = $this->createQueryBuilder('city')
            ->andWhere('city.country = :country')
            ->andWhere('city.slug = :slug')
            ->setParameter('country', $country)
            ->setParameter('slug', $slug)
            ->setMaxResults(2)
            ->getQuery()
            ->getResult();

        return \count($results) === 1 && $results[0] instanceof City ? $results[0] : null;
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
     * @return PaginatedResult<City>
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
