<?php

namespace App\Modules\Transfer\Repository;

use App\Modules\Transfer\Entity\TransferProduct;
use App\Modules\Transfer\ValueObject\TransferEndpointContext;
use App\Shared\Admin\Pagination\PaginatedResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TransferProduct>
 */
class TransferProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TransferProduct::class);
    }

    /**
     * @param array{q: string, transferType: string, active: string, featured: string, publicVisible: string, page: int, pageSize: int} $filters
     *
     * @return PaginatedResult<TransferProduct>
     */
    public function findForAdminPage(array $filters): PaginatedResult
    {
        $builder = $this->baseListBuilder()
            ->orderBy('product.id', 'DESC');

        if ($filters['q'] !== '') {
            $builder
                ->andWhere('product.name LIKE :query OR product.nameFa LIKE :query')
                ->setParameter('query', '%' . $filters['q'] . '%');
        }

        if ($filters['transferType'] !== '') {
            $builder->andWhere('product.transferType = :transferType')->setParameter('transferType', $filters['transferType']);
        }

        $this->applyBooleanFilter($builder, 'product.active', 'active', $filters['active']);
        $this->applyBooleanFilter($builder, 'product.featured', 'featured', $filters['featured']);
        $this->applyBooleanFilter($builder, 'product.publicVisible', 'publicVisible', $filters['publicVisible']);

        return $this->paginate($builder, $filters['page'], $filters['pageSize'], $filters);
    }

    /**
     * Cheap pre-filter for the resolver: active own products whose origin
     * and destination endpoints match the requested context. Final date and
     * passenger applicability is checked in PHP via TransferOffer::matches().
     *
     * @return TransferProduct[]
     */
    public function findMatchingOwnProducts(TransferEndpointContext $from, TransferEndpointContext $to): array
    {
        $builder = $this->createQueryBuilder('product')
            ->andWhere('product.active = true');

        $this->applyEndpointFilter($builder, 'origin', $from);
        $this->applyEndpointFilter($builder, 'destination', $to);

        return $builder->getQuery()->getResult();
    }

    private function applyEndpointFilter(QueryBuilder $builder, string $side, TransferEndpointContext $context): void
    {
        if ($context->airport !== null) {
            $builder->andWhere(sprintf('product.%sAirport = :%sAirport', $side, $side))->setParameter($side . 'Airport', $context->airport);

            return;
        }

        if ($context->city !== null) {
            $builder->andWhere(sprintf('product.%sCity = :%sCity', $side, $side))->setParameter($side . 'City', $context->city);

            return;
        }

        $builder->andWhere(sprintf('product.%sHotel = :%sHotel', $side, $side))->setParameter($side . 'Hotel', $context->hotel);
    }

    private function baseListBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('product')
            ->leftJoin('product.originAirport', 'originAirport')
            ->leftJoin('product.originCity', 'originCity')
            ->leftJoin('product.originHotel', 'originHotel')
            ->leftJoin('product.destinationAirport', 'destinationAirport')
            ->leftJoin('product.destinationCity', 'destinationCity')
            ->leftJoin('product.destinationHotel', 'destinationHotel')
            ->addSelect('originAirport', 'originCity', 'originHotel', 'destinationAirport', 'destinationCity', 'destinationHotel');
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
     * @return PaginatedResult<TransferProduct>
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
