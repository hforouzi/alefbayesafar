<?php

namespace App\Modules\Destination\Repository;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\ValueObject\PaginatedResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
            ->addSelect('city', 'state', 'country')
            ->innerJoin('airport.city', 'city')
            ->leftJoin('city.state', 'state')
            ->innerJoin('city.country', 'country')
            ->orderBy('country.name', 'ASC')
            ->addOrderBy('state.name', 'ASC')
            ->addOrderBy('city.name', 'ASC')
            ->addOrderBy('airport.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array{q: string, name: string, nameFa: string, iata: string, icao: string, country: int|null, state: int|null, city: int|null, active: string, page: int, pageSize: int} $filters
     *
     * @return PaginatedResult<Airport>
     */
    public function findForAdminPage(array $filters): PaginatedResult
    {
        $builder = $this->createQueryBuilder('airport')
            ->addSelect('city', 'state', 'country')
            ->innerJoin('airport.city', 'city')
            ->leftJoin('city.state', 'state')
            ->innerJoin('city.country', 'country')
            ->orderBy('airport.iataCode', 'ASC')
            ->addOrderBy('airport.name', 'ASC');

        if ($filters['q'] !== '') {
            $builder
                ->andWhere('airport.name LIKE :globalQuery OR airport.nameFa LIKE :globalQuery OR airport.iataCode LIKE :globalCode OR airport.icaoCode LIKE :globalCode OR city.name LIKE :globalQuery OR city.nameFa LIKE :globalQuery OR state.name LIKE :globalQuery OR state.nameFa LIKE :globalQuery OR country.name LIKE :globalQuery OR country.nameFa LIKE :globalQuery OR country.iso2 LIKE :globalCode')
                ->setParameter('globalQuery', '%' . $filters['q'] . '%')
                ->setParameter('globalCode', strtoupper($filters['q']) . '%');
        }
        $this->applyTextFilter($builder, 'airport.name', 'name', $filters['name']);
        $this->applyTextFilter($builder, 'airport.nameFa', 'nameFa', $filters['nameFa']);
        if ($filters['iata'] !== '') {
            $builder->andWhere('airport.iataCode LIKE :iata')->setParameter('iata', $filters['iata'] . '%');
        }
        if ($filters['icao'] !== '') {
            $builder->andWhere('airport.icaoCode LIKE :icao')->setParameter('icao', $filters['icao'] . '%');
        }
        if ($filters['country'] !== null) {
            $builder->andWhere('country.id = :countryId')->setParameter('countryId', $filters['country']);
        }
        if ($filters['state'] !== null) {
            $builder->andWhere('state.id = :stateId')->setParameter('stateId', $filters['state']);
        }
        if ($filters['city'] !== null) {
            $builder->andWhere('city.id = :cityId')->setParameter('cityId', $filters['city']);
        }

        $this->applyActiveFilter($builder, $filters['active'], 'airport');

        return $this->paginate($builder, $filters['page'], $filters['pageSize'], $filters);
    }

    /**
     * @return Airport[]
     */
    public function search(string $query, ?City $city = null, int $limit = 25): array
    {
        $query = trim($query);
        $builder = $this->createQueryBuilder('airport')
            ->addSelect('city', 'country')
            ->innerJoin('airport.city', 'city')
            ->innerJoin('city.country', 'country')
            ->andWhere('airport.name LIKE :query OR airport.nameFa LIKE :query OR airport.iataCode LIKE :code OR airport.icaoCode LIKE :code')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('code', strtoupper($query) . '%')
            ->orderBy('airport.iataCode', 'ASC')
            ->addOrderBy('airport.name', 'ASC')
            ->setMaxResults($limit);

        if ($city instanceof City) {
            $builder->andWhere('airport.city = :city')->setParameter('city', $city);
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
     * @return PaginatedResult<Airport>
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
