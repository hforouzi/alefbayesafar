<?php

namespace App\Modules\Hotel\Repository;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\District;
use App\Modules\Hotel\Entity\Hotel;
use App\Shared\Admin\Pagination\PaginatedResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Hotel>
 */
class HotelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Hotel::class);
    }

    /**
     * @param array{q: string, name: string, nameFa: string, city: int|null, district: int|null, stars: string, active: string, verified: string, page: int, pageSize: int} $filters
     *
     * @return PaginatedResult<Hotel>
     */
    public function findForAdminPage(array $filters): PaginatedResult
    {
        $builder = $this->createAdminListBuilder();

        if ($filters['q'] !== '') {
            $builder
                ->andWhere('hotel.name LIKE :globalQuery OR hotel.nameFa LIKE :globalQuery OR hotel.slug LIKE :globalQuery OR city.name LIKE :globalQuery OR city.nameFa LIKE :globalQuery OR district.name LIKE :globalQuery OR district.nameFa LIKE :globalQuery OR country.name LIKE :globalQuery OR country.nameFa LIKE :globalQuery')
                ->setParameter('globalQuery', '%' . $filters['q'] . '%');
        }

        $this->applyTextFilter($builder, 'hotel.name', 'name', $filters['name']);
        $this->applyTextFilter($builder, 'hotel.nameFa', 'nameFa', $filters['nameFa']);

        if ($filters['city'] !== null) {
            $builder->andWhere('city.id = :cityId')->setParameter('cityId', $filters['city']);
        }

        if ($filters['district'] !== null) {
            $builder->andWhere('district.id = :districtId')->setParameter('districtId', $filters['district']);
        }

        if ($filters['stars'] !== '' && ctype_digit($filters['stars'])) {
            $builder->andWhere('hotel.stars = :stars')->setParameter('stars', (int) $filters['stars']);
        }

        $this->applyBooleanFilter($builder, $filters['active'], 'hotel.active', 'active');
        $this->applyBooleanFilter($builder, $filters['verified'], 'hotel.verified', 'verified');

        return $this->paginate($builder, $filters['page'], $filters['pageSize'], $filters);
    }

    public function findWithDetails(int $id): ?Hotel
    {
        $result = $this->createAdminListBuilder()
            ->addSelect('amenity', 'image', 'sourceReference')
            ->leftJoin('hotel.amenities', 'amenity')
            ->leftJoin('hotel.images', 'image')
            ->leftJoin('hotel.sourceReferences', 'sourceReference')
            ->andWhere('hotel.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof Hotel ? $result : null;
    }

    public function districtBelongsToCity(?District $district, ?City $city): bool
    {
        if (!$district instanceof District) {
            return true;
        }

        return $city instanceof City && $district->getCity() === $city;
    }

    private function createAdminListBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('hotel')
            ->addSelect('city', 'state', 'country', 'district', 'primaryImage')
            ->innerJoin('hotel.city', 'city')
            ->leftJoin('city.state', 'state')
            ->innerJoin('city.country', 'country')
            ->leftJoin('hotel.district', 'district')
            ->leftJoin('hotel.images', 'primaryImage', 'WITH', 'primaryImage.primary = :primaryImage')
            ->setParameter('primaryImage', true)
            ->orderBy('hotel.updatedAt', 'DESC')
            ->addOrderBy('hotel.name', 'ASC');
    }

    private function applyBooleanFilter(QueryBuilder $builder, string $value, string $field, string $parameter): void
    {
        if ($value === '1' || $value === '0') {
            $builder
                ->andWhere($field . ' = :' . $parameter)
                ->setParameter($parameter, $value === '1');
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
     * @return PaginatedResult<Hotel>
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
