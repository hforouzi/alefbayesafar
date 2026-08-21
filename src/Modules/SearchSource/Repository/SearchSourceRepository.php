<?php

namespace App\Modules\SearchSource\Repository;

use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Shared\Admin\Pagination\PaginatedResult;
use App\Modules\Destination\Entity\Country;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SearchSource>
 */
class SearchSourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SearchSource::class);
    }

    /**
     * @param array{q: string, providerType: string, country: int|null, enabled: string, page: int, pageSize: int} $filters
     *
     * @return PaginatedResult<SearchSource>
     */
    public function findForAdminPage(array $filters): PaginatedResult
    {
        $builder = $this->createQueryBuilder('source')
            ->addSelect('country')
            ->leftJoin('source.country', 'country')
            ->orderBy('source.priority', 'ASC')
            ->addOrderBy('source.name', 'ASC');

        if ($filters['q'] !== '') {
            $builder
                ->andWhere('source.name LIKE :query OR source.domain LIKE :query OR source.provider LIKE :query OR source.language LIKE :query OR country.name LIKE :query OR country.nameFa LIKE :query')
                ->setParameter('query', '%' . $filters['q'] . '%');
        }

        if ($filters['providerType'] !== '' && SearchSourceProviderType::tryFrom($filters['providerType']) instanceof SearchSourceProviderType) {
            $builder
                ->andWhere('source.providerType = :providerType')
                ->setParameter('providerType', SearchSourceProviderType::from($filters['providerType']));
        }

        if ($filters['country'] !== null) {
            $builder->andWhere('country.id = :countryId')->setParameter('countryId', $filters['country']);
        }

        if ($filters['enabled'] === '1' || $filters['enabled'] === '0') {
            $builder->andWhere('source.enabled = :enabled')->setParameter('enabled', $filters['enabled'] === '1');
        }

        return $this->paginate($builder, $filters['page'], $filters['pageSize'], $filters);
    }

    /**
     * @return SearchSource[]
     */
    public function findEnabledForCapability(string $capability, ?Country $country = null): array
    {
        $builder = $this->createQueryBuilder('source')
            ->addSelect('country')
            ->leftJoin('source.country', 'country')
            ->andWhere('source.enabled = :enabled')
            ->andWhere('source.capabilities LIKE :capability')
            ->setParameter('enabled', true)
            ->setParameter('capability', '%"' . strtolower($capability) . '"%')
            ->orderBy('source.priority', 'ASC')
            ->addOrderBy('source.name', 'ASC')
            ->addOrderBy('source.id', 'ASC');

        if ($country instanceof Country) {
            $builder
                ->andWhere('source.country IS NULL OR source.country = :country')
                ->setParameter('country', $country);
        } else {
            $builder->andWhere('source.country IS NULL');
        }

        return $builder->getQuery()->getResult();
    }

    /**
     * @param array<string, scalar|null> $filters
     *
     * @return PaginatedResult<SearchSource>
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
