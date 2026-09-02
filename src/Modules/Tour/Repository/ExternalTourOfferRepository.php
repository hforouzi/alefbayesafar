<?php

namespace App\Modules\Tour\Repository;

use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\Tour\Entity\ExternalTourOffer;
use App\Modules\Tour\Enum\TourAvailabilityStatus;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExternalTourOffer>
 */
class ExternalTourOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExternalTourOffer::class);
    }

    public function deleteExternalSnapshot(SearchSource $source, ExternalTourOfferSearchRequest $request): int
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
     * @return ExternalTourOffer[]
     */
    public function findExternalSnapshotOffers(SearchSource $source, ExternalTourOfferSearchRequest $request): array
    {
        return $this->createQueryBuilder('offer')
            ->leftJoin('offer.originAirport', 'origin')
            ->leftJoin('offer.destinationCity', 'destination')
            ->leftJoin('offer.hotel', 'hotel')
            ->leftJoin('offer.hotelRoomType', 'roomType')
            ->addSelect('origin', 'destination', 'hotel', 'roomType')
            ->andWhere('offer.searchSource = :source')
            ->andWhere('offer.adults = :adults')
            ->andWhere('offer.children = :children')
            ->andWhere('offer.infants = :infants')
            ->setParameter('source', $source)
            ->setParameter('adults', $request->adults)
            ->setParameter('children', $request->children)
            ->setParameter('infants', $request->infants)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ExternalTourOffer[]
     */
    public function findFreshPotentialMatches(ExternalTourOfferSearchRequest $request, \DateTimeImmutable $now): array
    {
        $builder = $this->createQueryBuilder('offer')
            ->leftJoin('offer.originAirport', 'origin')
            ->leftJoin('offer.destinationCity', 'destination')
            ->leftJoin('offer.hotel', 'hotel')
            ->leftJoin('offer.hotelRoomType', 'roomType')
            ->addSelect('origin', 'destination', 'hotel', 'roomType')
            ->andWhere('offer.expiresAt > :now')
            ->andWhere('offer.availabilityStatus != :unavailable')
            ->setParameter('now', $now)
            ->setParameter('unavailable', TourAvailabilityStatus::UNAVAILABLE)
            ->orderBy('offer.totalPrice', 'ASC')
            ->addOrderBy('offer.id', 'DESC');

        return array_values(array_filter(
            $builder->getQuery()->getResult(),
            static fn (ExternalTourOffer $offer): bool => ($offer->getMetadata()['searchContextHash'] ?? null) === $request->contextHash(),
        ));
    }
}
