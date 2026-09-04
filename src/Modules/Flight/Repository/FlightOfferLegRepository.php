<?php

namespace App\Modules\Flight\Repository;

use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Entity\FlightOfferLeg;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FlightOfferLeg>
 */
class FlightOfferLegRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FlightOfferLeg::class);
    }

    /**
     * @return FlightOfferLeg[]
     */
    public function findForOffer(FlightOffer $offer): array
    {
        return $this->createQueryBuilder('leg')
            ->addSelect('airline', 'origin', 'destination')
            ->leftJoin('leg.airline', 'airline')
            ->innerJoin('leg.originAirport', 'origin')
            ->innerJoin('leg.destinationAirport', 'destination')
            ->andWhere('leg.flightOffer = :offer')
            ->setParameter('offer', $offer)
            ->orderBy('leg.direction', 'ASC')
            ->addOrderBy('leg.segmentIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
