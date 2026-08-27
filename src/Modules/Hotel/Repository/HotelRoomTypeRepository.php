<?php

namespace App\Modules\Hotel\Repository;

use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelRoomType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HotelRoomType>
 */
class HotelRoomTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HotelRoomType::class);
    }

    /**
     * @return HotelRoomType[]
     */
    public function findForHotel(Hotel $hotel): array
    {
        return $this->createQueryBuilder('roomType')
            ->andWhere('roomType.hotel = :hotel')
            ->setParameter('hotel', $hotel)
            ->orderBy('roomType.active', 'DESC')
            ->addOrderBy('roomType.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return HotelRoomType[]
     */
    public function findActiveForHotel(Hotel $hotel): array
    {
        return $this->createQueryBuilder('roomType')
            ->andWhere('roomType.hotel = :hotel')
            ->andWhere('roomType.active = true')
            ->setParameter('hotel', $hotel)
            ->orderBy('roomType.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countActiveForHotel(Hotel $hotel): int
    {
        return (int) $this->createQueryBuilder('roomType')
            ->select('COUNT(roomType.id)')
            ->andWhere('roomType.hotel = :hotel')
            ->andWhere('roomType.active = true')
            ->setParameter('hotel', $hotel)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countImportedForHotel(Hotel $hotel): int
    {
        return (int) $this->createQueryBuilder('roomType')
            ->select('COUNT(roomType.id)')
            ->andWhere('roomType.hotel = :hotel')
            ->andWhere('roomType.source IS NOT NULL')
            ->setParameter('hotel', $hotel)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneBySourceExternalId(Hotel $hotel, string $source, string $externalId): ?HotelRoomType
    {
        return $this->createQueryBuilder('roomType')
            ->andWhere('roomType.hotel = :hotel')
            ->andWhere('roomType.source = :source')
            ->andWhere('roomType.externalId = :externalId')
            ->setParameter('hotel', $hotel)
            ->setParameter('source', strtolower(trim($source)))
            ->setParameter('externalId', trim($externalId))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByNormalizedName(Hotel $hotel, string $name): ?HotelRoomType
    {
        $normalized = self::normalizeName($name);
        if ($normalized === '') {
            return null;
        }

        foreach ($this->findForHotel($hotel) as $roomType) {
            if (self::normalizeName($roomType->getName()) === $normalized || self::normalizeName((string) $roomType->getSourceName()) === $normalized) {
                return $roomType;
            }
        }

        return null;
    }

    public static function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/u', ' ', $name) ?? '';

        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }
}
