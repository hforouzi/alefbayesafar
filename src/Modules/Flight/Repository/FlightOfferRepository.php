<?php

namespace App\Modules\Flight\Repository;

use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\Flight\Enum\FlightAvailabilityStatus;
use App\Modules\Flight\Enum\FlightPriceSourceType;
use App\Modules\Flight\Enum\FlightTripType;
use App\Modules\Flight\ValueObject\FlightOfferSearchRequest;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Shared\Admin\Pagination\PaginatedResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FlightOffer>
 */
class FlightOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FlightOffer::class);
    }

    /**
     * @param array{q: string, tripType: string, cabinClass: string, active: string, page: int, pageSize: int} $filters
     *
     * @return PaginatedResult<FlightOffer>
     */
    public function findOwnForAdminPage(array $filters): PaginatedResult
    {
        $builder = $this->createQueryBuilder('offer')
            ->leftJoin('offer.legs', 'leg')
            ->leftJoin('leg.airline', 'airline')
            ->leftJoin('leg.originAirport', 'origin')
            ->leftJoin('leg.destinationAirport', 'destination')
            ->addSelect('leg', 'airline', 'origin', 'destination')
            ->andWhere('offer.sourceType = :sourceType')
            ->setParameter('sourceType', FlightPriceSourceType::OWN)
            ->orderBy('offer.priority', 'DESC')
            ->addOrderBy('offer.id', 'DESC')
            ->addOrderBy('leg.direction', 'ASC')
            ->addOrderBy('leg.segmentIndex', 'ASC');

        if ($filters['q'] !== '') {
            $builder
                ->andWhere('airline.name LIKE :query OR airline.nameFa LIKE :query OR airline.iataCode LIKE :code OR leg.flightNumber LIKE :code OR origin.iataCode LIKE :code OR destination.iataCode LIKE :code OR origin.name LIKE :query OR destination.name LIKE :query')
                ->setParameter('query', '%' . $filters['q'] . '%')
                ->setParameter('code', strtoupper($filters['q']) . '%');
        }

        if ($filters['tripType'] !== '' && FlightTripType::tryFrom($filters['tripType']) instanceof FlightTripType) {
            $builder->andWhere('offer.tripType = :tripType')->setParameter('tripType', FlightTripType::from($filters['tripType']));
        }

        if ($filters['cabinClass'] !== '' && FlightCabinClass::tryFrom($filters['cabinClass']) instanceof FlightCabinClass) {
            $builder->andWhere('offer.cabinClass = :cabinClass')->setParameter('cabinClass', FlightCabinClass::from($filters['cabinClass']));
        }

        if ($filters['active'] === '1' || $filters['active'] === '0') {
            $builder->andWhere('offer.active = :active')->setParameter('active', $filters['active'] === '1');
        }

        return $this->paginate($builder, $filters['page'], $filters['pageSize'], $filters);
    }

    /**
     * @return FlightOffer[]
     */
    public function findPotentialOwnMatches(FlightOfferSearchRequest $request): array
    {
        return $this->createQueryBuilder('offer')
            ->leftJoin('offer.legs', 'leg')
            ->leftJoin('leg.airline', 'airline')
            ->leftJoin('leg.originAirport', 'origin')
            ->leftJoin('leg.destinationAirport', 'destination')
            ->addSelect('leg', 'airline', 'origin', 'destination')
            ->andWhere('offer.sourceType = :sourceType')
            ->andWhere('offer.active = :active')
            ->andWhere('offer.tripType = :tripType')
            ->andWhere('offer.cabinClass = :cabinClass')
            ->setParameter('sourceType', FlightPriceSourceType::OWN)
            ->setParameter('active', true)
            ->setParameter('tripType', $request->tripType)
            ->setParameter('cabinClass', $request->cabinClass)
            ->orderBy('offer.priority', 'DESC')
            ->addOrderBy('offer.id', 'DESC')
            ->addOrderBy('leg.direction', 'ASC')
            ->addOrderBy('leg.segmentIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return FlightOffer[]
     */
    public function findPotentialExternalMatches(FlightOfferSearchRequest $request, \DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('offer')
            ->leftJoin('offer.legs', 'leg')
            ->leftJoin('leg.airline', 'airline')
            ->leftJoin('leg.originAirport', 'origin')
            ->leftJoin('leg.destinationAirport', 'destination')
            ->addSelect('leg', 'airline', 'origin', 'destination')
            ->andWhere('offer.sourceType = :sourceType')
            ->andWhere('offer.active = :active')
            ->andWhere('offer.tripType = :tripType')
            ->andWhere('offer.cabinClass = :cabinClass')
            ->andWhere('offer.adults = :adults')
            ->andWhere('offer.children = :children')
            ->andWhere('offer.infants = :infants')
            ->andWhere('offer.availabilityStatus != :unavailable')
            ->andWhere('offer.expiresAt > :now')
            ->setParameter('sourceType', FlightPriceSourceType::EXTERNAL)
            ->setParameter('active', true)
            ->setParameter('tripType', $request->tripType)
            ->setParameter('cabinClass', $request->cabinClass)
            ->setParameter('adults', $request->adults)
            ->setParameter('children', $request->children)
            ->setParameter('infants', $request->infants)
            ->setParameter('unavailable', FlightAvailabilityStatus::UNAVAILABLE)
            ->setParameter('now', $now)
            ->orderBy('offer.priority', 'DESC')
            ->addOrderBy('offer.id', 'DESC')
            ->addOrderBy('leg.direction', 'ASC')
            ->addOrderBy('leg.segmentIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return FlightOffer[]
     */
    public function findExternalSnapshotOffers(SearchSource $source, FlightOfferSearchRequest $request): array
    {
        return $this->createQueryBuilder('offer')
            ->leftJoin('offer.legs', 'leg')
            ->addSelect('leg')
            ->andWhere('offer.sourceType = :sourceType')
            ->andWhere('offer.searchSource = :source')
            ->andWhere('offer.tripType = :tripType')
            ->andWhere('offer.cabinClass = :cabinClass')
            ->andWhere('offer.adults = :adults')
            ->andWhere('offer.children = :children')
            ->andWhere('offer.infants = :infants')
            ->setParameter('sourceType', FlightPriceSourceType::EXTERNAL)
            ->setParameter('source', $source)
            ->setParameter('tripType', $request->tripType)
            ->setParameter('cabinClass', $request->cabinClass)
            ->setParameter('adults', $request->adults)
            ->setParameter('children', $request->children)
            ->setParameter('infants', $request->infants)
            ->getQuery()
            ->getResult();
    }

    public function deleteExternalSnapshot(SearchSource $source, FlightOfferSearchRequest $request): int
    {
        $deleted = 0;
        foreach ($this->findExternalSnapshotOffers($source, $request) as $offer) {
            if (($offer->getMetadata()['searchContextHash'] ?? null) !== $request->contextHash()) {
                continue;
            }

            $this->getEntityManager()->remove($offer);
            ++$deleted;
        }

        return $deleted;
    }

    /**
     * @param array<string, scalar|null> $filters
     *
     * @return PaginatedResult<FlightOffer>
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
