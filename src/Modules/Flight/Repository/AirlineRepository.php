<?php

namespace App\Modules\Flight\Repository;

use App\Modules\Flight\Entity\Airline;
use App\Shared\Admin\Pagination\PaginatedResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Airline>
 */
class AirlineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Airline::class);
    }

    /**
     * @return Airline[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('airline')
            ->andWhere('airline.active = :active')
            ->setParameter('active', true)
            ->orderBy('airline.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByIataCode(string $iataCode): ?Airline
    {
        return $this->findOneBy(['iataCode' => strtoupper(trim($iataCode))]);
    }

    /**
     * @param array{q: string, name: string, nameFa: string, iata: string, icao: string, active: string, page: int, pageSize: int} $filters
     *
     * @return PaginatedResult<Airline>
     */
    public function findForAdminPage(array $filters): PaginatedResult
    {
        $builder = $this->createQueryBuilder('airline')
            ->leftJoin('airline.country', 'country')
            ->addSelect('country')
            ->orderBy('airline.name', 'ASC');

        if ($filters['q'] !== '') {
            $builder
                ->andWhere('airline.name LIKE :query OR airline.nameFa LIKE :query OR airline.iataCode LIKE :code OR airline.icaoCode LIKE :code OR country.name LIKE :query OR country.nameFa LIKE :query')
                ->setParameter('query', '%' . $filters['q'] . '%')
                ->setParameter('code', strtoupper($filters['q']) . '%');
        }

        $this->applyTextFilter($builder, 'airline.name', 'name', $filters['name']);
        $this->applyTextFilter($builder, 'airline.nameFa', 'nameFa', $filters['nameFa']);

        if ($filters['iata'] !== '') {
            $builder->andWhere('airline.iataCode LIKE :iata')->setParameter('iata', $filters['iata'] . '%');
        }

        if ($filters['icao'] !== '') {
            $builder->andWhere('airline.icaoCode LIKE :icao')->setParameter('icao', $filters['icao'] . '%');
        }

        if ($filters['active'] === '1' || $filters['active'] === '0') {
            $builder->andWhere('airline.active = :active')->setParameter('active', $filters['active'] === '1');
        }

        return $this->paginate($builder, $filters['page'], $filters['pageSize'], $filters);
    }

    /**
     * @return Airline[]
     */
    public function search(string $query, int $limit = 25): array
    {
        $query = trim($query);

        return $this->createQueryBuilder('airline')
            ->andWhere('airline.active = :active')
            ->andWhere('airline.name LIKE :query OR airline.nameFa LIKE :query OR airline.iataCode LIKE :code OR airline.icaoCode LIKE :code')
            ->setParameter('active', true)
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('code', strtoupper($query) . '%')
            ->orderBy('airline.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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
     * @return PaginatedResult<Airline>
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
