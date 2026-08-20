<?php

namespace App\Modules\Destination\Repository;

use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Entity\State;
use App\Modules\Destination\ValueObject\PaginatedResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<State>
 */
class StateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, State::class);
    }

    /**
     * @return State[]
     */
    public function findForAdminList(): array
    {
        return $this->createQueryBuilder('state')
            ->addSelect('country')
            ->innerJoin('state.country', 'country')
            ->orderBy('country.name', 'ASC')
            ->addOrderBy('state.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array{q: string, name: string, nameFa: string, country: int|null, code: string, active: string, page: int, pageSize: int} $filters
     *
     * @return PaginatedResult<State>
     */
    public function findForAdminPage(array $filters): PaginatedResult
    {
        $builder = $this->createQueryBuilder('state')
            ->addSelect('country')
            ->innerJoin('state.country', 'country')
            ->orderBy('country.name', 'ASC')
            ->addOrderBy('state.name', 'ASC');

        if ($filters['q'] !== '') {
            $builder
                ->andWhere('state.name LIKE :globalQuery OR state.nameFa LIKE :globalQuery OR state.code LIKE :globalCode OR country.name LIKE :globalQuery OR country.nameFa LIKE :globalQuery OR country.iso2 LIKE :globalCode')
                ->setParameter('globalQuery', '%' . $filters['q'] . '%')
                ->setParameter('globalCode', strtoupper($filters['q']) . '%');
        }
        $this->applyTextFilter($builder, 'state.name', 'name', $filters['name']);
        $this->applyTextFilter($builder, 'state.nameFa', 'nameFa', $filters['nameFa']);
        if ($filters['country'] !== null) {
            $builder->andWhere('country.id = :countryId')->setParameter('countryId', $filters['country']);
        }
        if ($filters['code'] !== '') {
            $builder->andWhere('state.code LIKE :code')->setParameter('code', $filters['code'] . '%');
        }

        $this->applyActiveFilter($builder, $filters['active'], 'state');

        return $this->paginate($builder, $filters['page'], $filters['pageSize'], $filters);
    }

    public function findOneByCountryAndCode(Country $country, string $code): ?State
    {
        return $this->findOneBy([
            'country' => $country,
            'code' => strtoupper(trim($code)),
        ]);
    }

    /**
     * @return State[]
     */
    public function search(string $query, ?Country $country = null, int $limit = 25): array
    {
        $builder = $this->createQueryBuilder('state')
            ->addSelect('country')
            ->innerJoin('state.country', 'country')
            ->andWhere('state.name LIKE :query OR state.nameFa LIKE :query OR state.code LIKE :code')
            ->setParameter('query', '%' . trim($query) . '%')
            ->setParameter('code', strtoupper(trim($query)) . '%')
            ->orderBy('country.name', 'ASC')
            ->addOrderBy('state.name', 'ASC')
            ->setMaxResults($limit);

        if ($country instanceof Country) {
            $builder
                ->andWhere('state.country = :country')
                ->setParameter('country', $country);
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
     * @return PaginatedResult<State>
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
