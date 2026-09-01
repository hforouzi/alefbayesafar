<?php

namespace App\Modules\Tour\Repository;

use App\Modules\Destination\Entity\City;
use App\Modules\Tour\Entity\TourPackage;
use App\Shared\Admin\Pagination\PaginatedResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TourPackage>
 */
class TourPackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TourPackage::class);
    }

    /**
     * @param array{q: string, destination: int|null, active: string, featured: string, publicVisible: string, page: int, pageSize: int} $filters
     *
     * @return PaginatedResult<TourPackage>
     */
    public function findForAdminPage(array $filters): PaginatedResult
    {
        $builder = $this->baseListBuilder()
            ->orderBy('package.priority', 'DESC')
            ->addOrderBy('package.id', 'DESC');

        if ($filters['q'] !== '') {
            $builder
                ->andWhere('package.name LIKE :query OR package.nameFa LIKE :query OR package.slug LIKE :code OR hotel.name LIKE :query OR hotel.nameFa LIKE :query OR flightLeg.flightNumber LIKE :code OR origin.iataCode LIKE :code OR destination.name LIKE :query OR destination.nameFa LIKE :query')
                ->setParameter('query', '%' . $filters['q'] . '%')
                ->setParameter('code', strtoupper($filters['q']) . '%');
        }

        if ($filters['destination'] !== null) {
            $builder->andWhere('destination.id = :destination')->setParameter('destination', $filters['destination']);
        }

        $this->applyBooleanFilter($builder, 'package.active', 'active', $filters['active']);
        $this->applyBooleanFilter($builder, 'package.featured', 'featured', $filters['featured']);
        $this->applyBooleanFilter($builder, 'package.publicVisible', 'publicVisible', $filters['publicVisible']);

        return $this->paginate($builder, $filters['page'], $filters['pageSize'], $filters);
    }

    /**
     * @return TourPackage[]
     */
    public function findActivePublicVisible(?City $destination = null): array
    {
        $builder = $this->baseListBuilder()
            ->andWhere('package.active = :active')
            ->andWhere('package.publicVisible = :publicVisible')
            ->setParameter('active', true)
            ->setParameter('publicVisible', true)
            ->orderBy('package.featured', 'DESC')
            ->addOrderBy('package.priority', 'DESC')
            ->addOrderBy('package.id', 'DESC');

        if ($destination instanceof City) {
            $builder->andWhere('package.destinationCity = :destination')->setParameter('destination', $destination);
        }

        return $builder->getQuery()->getResult();
    }

    private function baseListBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('package')
            ->leftJoin('package.originAirport', 'origin')
            ->leftJoin('package.destinationCity', 'destination')
            ->leftJoin('package.flightOffer', 'flight')
            ->leftJoin('flight.legs', 'flightLeg')
            ->leftJoin('package.hotel', 'hotel')
            ->leftJoin('package.hotelRoomType', 'roomType')
            ->leftJoin('package.images', 'image')
            ->addSelect('origin', 'destination', 'flight', 'flightLeg', 'hotel', 'roomType', 'image');
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
     * @return PaginatedResult<TourPackage>
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
