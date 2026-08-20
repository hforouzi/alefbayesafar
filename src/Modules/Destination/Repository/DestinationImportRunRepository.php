<?php

namespace App\Modules\Destination\Repository;

use App\Modules\Destination\Entity\DestinationImportRun;
use App\Modules\Destination\ValueObject\PaginatedResult;
use App\Shared\Date\LocaleDateTimeFormatter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DestinationImportRun>
 */
class DestinationImportRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly LocaleDateTimeFormatter $dateTimeFormatter)
    {
        parent::__construct($registry, DestinationImportRun::class);
    }

    /**
     * @return DestinationImportRun[]
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('run')
            ->orderBy('run.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array{q: string, provider: string, targetType: string, country: string, status: string, startedFrom: string, startedTo: string, finishedFrom: string, finishedTo: string, page: int, pageSize: int} $filters
     *
     * @return PaginatedResult<DestinationImportRun>
     */
    public function findForAdminPage(array $filters): PaginatedResult
    {
        $builder = $this->createQueryBuilder('run')
            ->orderBy('run.startedAt', 'DESC');

        if ($filters['q'] !== '') {
            $builder
                ->andWhere('run.targetType LIKE :globalQuery OR run.countryName LIKE :globalQuery OR run.cityName LIKE :globalQuery OR run.providers LIKE :globalQuery')
                ->setParameter('globalQuery', '%' . $filters['q'] . '%');
        }
        if ($filters['provider'] !== '') {
            $builder
                ->andWhere('run.providers LIKE :provider')
                ->setParameter('provider', '%' . $filters['provider'] . '%');
        }

        if ($filters['targetType'] !== '') {
            $builder
                ->andWhere('run.targetType = :targetType')
                ->setParameter('targetType', strtolower($filters['targetType']));
        }

        if ($filters['country'] !== '') {
            $builder
                ->andWhere('run.countryName LIKE :country')
                ->setParameter('country', '%' . $filters['country'] . '%');
        }

        if (\in_array($filters['status'], [
            DestinationImportRun::STATUS_RUNNING,
            DestinationImportRun::STATUS_COMPLETED,
            DestinationImportRun::STATUS_COMPLETED_WITH_ERRORS,
            DestinationImportRun::STATUS_FAILED,
        ], true)) {
            $builder
                ->andWhere('run.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if ($filters['startedFrom'] !== '') {
            $builder
                ->andWhere('run.startedAt >= :startedFrom')
                ->setParameter('startedFrom', $this->dateTimeFormatter->startOfDay($filters['startedFrom']));
        }

        if ($filters['startedTo'] !== '') {
            $builder
                ->andWhere('run.startedAt <= :startedTo')
                ->setParameter('startedTo', $this->dateTimeFormatter->endOfDay($filters['startedTo']));
        }

        if ($filters['finishedFrom'] !== '') {
            $builder
                ->andWhere('run.finishedAt >= :finishedFrom')
                ->setParameter('finishedFrom', $this->dateTimeFormatter->startOfDay($filters['finishedFrom']));
        }

        if ($filters['finishedTo'] !== '') {
            $builder
                ->andWhere('run.finishedAt <= :finishedTo')
                ->setParameter('finishedTo', $this->dateTimeFormatter->endOfDay($filters['finishedTo']));
        }

        return $this->paginate($builder, $filters['page'], $filters['pageSize'], $filters);
    }

    public function findLatestForTargetProvider(string $targetType, string $provider, ?string $countryName = null, ?string $cityName = null): ?DestinationImportRun
    {
        $builder = $this->createQueryBuilder('run')
            ->andWhere('run.targetType = :targetType')
            ->andWhere('run.providers LIKE :provider')
            ->setParameter('targetType', $targetType)
            ->setParameter('provider', '%' . $provider . '%')
            ->orderBy('run.startedAt', 'DESC')
            ->setMaxResults(1);

        if ($countryName !== null) {
            $builder
                ->andWhere('run.countryName = :countryName')
                ->setParameter('countryName', $countryName);
        }

        if ($cityName !== null) {
            $builder
                ->andWhere('run.cityName = :cityName')
                ->setParameter('cityName', $cityName);
        } else {
            $builder->andWhere('run.cityName IS NULL');
        }

        $run = $builder->getQuery()->getOneOrNullResult();

        return $run instanceof DestinationImportRun ? $run : null;
    }

    /**
     * @param array<string, scalar|null> $filters
     *
     * @return PaginatedResult<DestinationImportRun>
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
