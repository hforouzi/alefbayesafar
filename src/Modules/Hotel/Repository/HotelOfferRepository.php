<?php

namespace App\Modules\Hotel\Repository;

use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelOffer;
use App\Modules\Hotel\ValueObject\HotelOfferSearchRequest;
use App\Modules\SearchSource\Entity\SearchSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HotelOffer>
 */
class HotelOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HotelOffer::class);
    }

    public function deleteForSearchContext(Hotel $hotel, SearchSource $source, HotelOfferSearchRequest $request): int
    {
        $offers = $this->createQueryBuilder('offer')
            ->andWhere('offer.hotel = :hotel')
            ->andWhere('offer.searchSource = :source')
            ->andWhere('offer.checkIn = :checkIn')
            ->andWhere('offer.checkOut = :checkOut')
            ->andWhere('offer.adults = :adults')
            ->andWhere('offer.children = :children')
            ->setParameter('hotel', $hotel)
            ->setParameter('source', $source)
            ->setParameter('checkIn', $request->checkIn, Types::DATE_IMMUTABLE)
            ->setParameter('checkOut', $request->checkOut, Types::DATE_IMMUTABLE)
            ->setParameter('adults', $request->adults)
            ->setParameter('children', $request->children)
            ->getQuery()
            ->getResult();

        $removed = 0;
        foreach ($offers as $offer) {
            if (!$offer instanceof HotelOffer || $offer->getChildrenAges() !== $request->childrenAges) {
                continue;
            }

            $this->getEntityManager()->remove($offer);
            ++$removed;
        }

        return $removed;
    }

    /**
     * @return HotelOffer[]
     */
    public function findRecentForHotel(Hotel $hotel, int $limit = 20): array
    {
        return $this->createQueryBuilder('offer')
            ->addSelect('source')
            ->innerJoin('offer.searchSource', 'source')
            ->andWhere('offer.hotel = :hotel')
            ->setParameter('hotel', $hotel)
            ->orderBy('offer.fetchedAt', 'DESC')
            ->addOrderBy('offer.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return HotelOffer[]
     */
    public function findForSearchContext(Hotel $hotel, HotelOfferSearchRequest $request): array
    {
        $offers = $this->createQueryBuilder('offer')
            ->addSelect('source')
            ->innerJoin('offer.searchSource', 'source')
            ->andWhere('offer.hotel = :hotel')
            ->andWhere('offer.checkIn = :checkIn')
            ->andWhere('offer.checkOut = :checkOut')
            ->andWhere('offer.adults = :adults')
            ->andWhere('offer.children = :children')
            ->setParameter('hotel', $hotel)
            ->setParameter('checkIn', $request->checkIn, Types::DATE_IMMUTABLE)
            ->setParameter('checkOut', $request->checkOut, Types::DATE_IMMUTABLE)
            ->setParameter('adults', $request->adults)
            ->setParameter('children', $request->children)
            ->orderBy('source.name', 'ASC')
            ->addOrderBy('offer.totalPrice', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values(array_filter($offers, static fn (HotelOffer $offer): bool => $offer->getChildrenAges() === $request->childrenAges));
    }
}
