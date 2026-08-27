<?php

namespace App\Modules\Hotel\Repository;

use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelRate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HotelRate>
 */
class HotelRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HotelRate::class);
    }

    /**
     * @return HotelRate[]
     */
    public function findForHotel(Hotel $hotel): array
    {
        return $this->createQueryBuilder('rate')
            ->addSelect('roomType')
            ->innerJoin('rate.roomType', 'roomType')
            ->andWhere('rate.hotel = :hotel')
            ->setParameter('hotel', $hotel)
            ->orderBy('rate.active', 'DESC')
            ->addOrderBy('rate.validFrom', 'ASC')
            ->addOrderBy('rate.priority', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $childrenAges
     *
     * @return HotelRate[]
     */
    public function findMatchingOwnRates(Hotel $hotel, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut, int $adults, int $children, array $childrenAges): array
    {
        $rates = $this->createQueryBuilder('rate')
            ->addSelect('roomType')
            ->innerJoin('rate.roomType', 'roomType')
            ->andWhere('rate.hotel = :hotel')
            ->andWhere('rate.active = true')
            ->andWhere('roomType.active = true')
            ->andWhere('rate.validFrom <= :checkIn')
            ->andWhere('rate.validTo >= :lastNight')
            ->andWhere('rate.adults = :adults')
            ->andWhere('rate.children = :children')
            ->setParameter('hotel', $hotel)
            ->setParameter('checkIn', $checkIn, Types::DATE_IMMUTABLE)
            ->setParameter('lastNight', $checkOut->modify('-1 day'), Types::DATE_IMMUTABLE)
            ->setParameter('adults', $adults)
            ->setParameter('children', $children)
            ->orderBy('rate.priority', 'DESC')
            ->addOrderBy('rate.pricePerNight', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values(array_filter($rates, static fn (HotelRate $rate): bool => $rate->matches($checkIn, $checkOut, $adults, $children, $childrenAges)));
    }

    public function countActiveForHotel(Hotel $hotel): int
    {
        return (int) $this->createQueryBuilder('rate')
            ->select('COUNT(rate.id)')
            ->andWhere('rate.hotel = :hotel')
            ->andWhere('rate.active = true')
            ->setParameter('hotel', $hotel)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
