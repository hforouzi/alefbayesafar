<?php

namespace App\Modules\Tour\Controller;

use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Enum\FlightPriceSourceType;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelRoomType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/tour-commerce/lookup')]
class TourLookupController extends AbstractController
{
    private const LIMIT = 25;

    #[Route('/hotels', name: 'tour_lookup_hotels', methods: ['GET'])]
    public function hotels(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $query = $this->query($request);
        $builder = $entityManager->getRepository(Hotel::class)->createQueryBuilder('hotel')
            ->leftJoin('hotel.city', 'city')
            ->addSelect('city')
            ->andWhere('hotel.active = true')
            ->orderBy('hotel.name', 'ASC')
            ->setMaxResults(self::LIMIT);

        if ($query !== '') {
            $builder
                ->andWhere('hotel.name LIKE :query OR hotel.nameFa LIKE :query OR hotel.slug LIKE :code OR city.name LIKE :query OR city.nameFa LIKE :query')
                ->setParameter('query', '%' . $query . '%')
                ->setParameter('code', strtolower($query) . '%');
        }

        return $this->json(['results' => array_map(
            static fn (Hotel $hotel): array => [
                'id' => $hotel->getId(),
                'text' => sprintf('%s - %s', (string) $hotel, $hotel->getCity()?->getName() ?? ''),
            ],
            $builder->getQuery()->getResult(),
        )]);
    }

    #[Route('/room-types', name: 'tour_lookup_room_types', methods: ['GET'])]
    public function roomTypes(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $query = $this->query($request);
        $hotelId = $request->query->getInt('hotel', $request->query->getInt('city'));
        $builder = $entityManager->getRepository(HotelRoomType::class)->createQueryBuilder('roomType')
            ->innerJoin('roomType.hotel', 'hotel')
            ->addSelect('hotel')
            ->andWhere('roomType.active = true')
            ->orderBy('hotel.name', 'ASC')
            ->addOrderBy('roomType.name', 'ASC')
            ->setMaxResults(self::LIMIT);

        if ($hotelId > 0) {
            $builder->andWhere('hotel.id = :hotelId')->setParameter('hotelId', $hotelId);
        }

        if ($query !== '') {
            $builder
                ->andWhere('roomType.name LIKE :query OR roomType.nameFa LIKE :query OR roomType.code LIKE :code OR hotel.name LIKE :query OR hotel.nameFa LIKE :query')
                ->setParameter('query', '%' . $query . '%')
                ->setParameter('code', strtolower($query) . '%');
        }

        return $this->json(['results' => array_map(
            static fn (HotelRoomType $roomType): array => [
                'id' => $roomType->getId(),
                'text' => sprintf('%s - %s', $roomType->getName(), (string) $roomType->getHotel()),
            ],
            $builder->getQuery()->getResult(),
        )]);
    }

    #[Route('/own-flight-offers', name: 'tour_lookup_own_flight_offers', methods: ['GET'])]
    public function ownFlightOffers(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $query = $this->query($request);
        $builder = $entityManager->getRepository(FlightOffer::class)->createQueryBuilder('offer')
            ->leftJoin('offer.legs', 'leg')
            ->leftJoin('leg.airline', 'airline')
            ->leftJoin('leg.originAirport', 'origin')
            ->leftJoin('leg.destinationAirport', 'destination')
            ->addSelect('leg', 'airline', 'origin', 'destination')
            ->andWhere('offer.sourceType = :sourceType')
            ->andWhere('offer.active = true')
            ->setParameter('sourceType', FlightPriceSourceType::OWN)
            ->orderBy('offer.priority', 'DESC')
            ->addOrderBy('offer.id', 'DESC')
            ->addOrderBy('leg.direction', 'ASC')
            ->addOrderBy('leg.segmentIndex', 'ASC')
            ->setMaxResults(self::LIMIT);

        if ($query !== '') {
            $builder
                ->andWhere('airline.name LIKE :query OR airline.nameFa LIKE :query OR leg.flightNumber LIKE :code OR origin.iataCode LIKE :code OR destination.iataCode LIKE :code OR origin.name LIKE :query OR destination.name LIKE :query')
                ->setParameter('query', '%' . $query . '%')
                ->setParameter('code', strtoupper($query) . '%');
        }

        return $this->json(['results' => array_map(
            static fn (FlightOffer $offer): array => [
                'id' => $offer->getId(),
                'text' => sprintf(
                    '%s - %s - %s - %s',
                    $offer->getRouteLabel(),
                    $offer->getDepartureLabel(),
                    $offer->getPrimaryAirlineLabel(),
                    $offer->getPriceLabel(),
                ),
            ],
            $builder->getQuery()->getResult(),
        )]);
    }

    private function query(Request $request): string
    {
        return mb_substr(trim((string) $request->query->get('q', '')), 0, 80);
    }
}
