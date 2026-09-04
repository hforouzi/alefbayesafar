<?php

namespace App\Modules\TripPlanner\Service;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Repository\AirportRepository;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Repository\SearchSourceRepository;
use App\Modules\TripPlanner\Enum\JourneyMode;
use App\Modules\TripPlanner\ValueObject\JourneyLeg;
use App\Modules\TripPlanner\ValueObject\JourneyOption;
use App\Modules\TripPlanner\ValueObject\TripSearchRequest;

final readonly class JourneyStrategyPlanner
{
    private const MAX_JOURNEY_STRATEGIES = 4;
    private const MAX_DEPARTURE_HUBS = 3;

    public function __construct(
        private AirportRepository $airportRepository,
        private ?SearchSourceRepository $searchSourceRepository = null,
    )
    {
    }

    /**
     * @return JourneyOption[]
     */
    public function strategies(TripSearchRequest $request): array
    {
        if (!$request->destinationCity instanceof City) {
            return [
                new JourneyOption(
                    'destination_free_discovery',
                    'Destination-free discovery',
                    [],
                    false,
                    ['Search can start from broad tour inventory when configured sources support it.'],
                    ['No destination-specific journey can be verified until a destination is selected.'],
                ),
            ];
        }

        $strategies = [];
        if ($request->originAirport instanceof Airport) {
            $strategies[] = new JourneyOption(
                'direct_air',
                'Direct air search',
                [new JourneyLeg(JourneyMode::FLIGHT, $this->airportLabel($request->originAirport), $this->cityLabel($request->destinationCity), false, 'Search direct flight/tour inventory for the requested route.')],
                false,
                ['Direct route inventory is checked where configured sources support it.'],
                ['This strategy is not proof that direct transport exists unless matching inventory is found.'],
            );
        }

        $originCity = $request->originCity ?? $request->originAirport?->getCity();
        if ($originCity instanceof City) {
            foreach ($this->departureHubs($originCity, $request->originAirport) as $hub) {
                $strategies[] = new JourneyOption(
                    'origin_to_hub_' . strtolower((string) $hub->getIataCode()),
                    'Reach departure hub then continue',
                    [
                        new JourneyLeg(JourneyMode::GROUND_TRANSFER, $this->cityLabel($originCity), $this->airportLabel($hub), false, 'Ground leg to a viable departure airport.'),
                        new JourneyLeg(JourneyMode::FLIGHT, $this->airportLabel($hub), $this->cityLabel($request->destinationCity), false, 'Search onward commercial inventory from this hub.'),
                    ],
                    false,
                    ['Alternative departure hub strategy.'],
                    ['Ground route feasibility and travel requirements are not verified yet.'],
                );
                if (\count($strategies) >= self::MAX_JOURNEY_STRATEGIES) {
                    return $strategies;
                }
            }
        }

        $strategies[] = new JourneyOption(
            'self_drive_boundary',
            'Self-drive feasibility check',
            [new JourneyLeg(JourneyMode::SELF_DRIVE, $originCity instanceof City ? $this->cityLabel($originCity) : 'Origin', $this->cityLabel($request->destinationCity), false, 'Represent a future road-routing/provider check.')],
            false,
            ['Self-drive can be represented as a strategy.'],
            ['Distance, border crossings, visa, vehicle documents, and insurance are not verified by current providers.'],
        );

        return array_slice($strategies, 0, self::MAX_JOURNEY_STRATEGIES);
    }

    /**
     * @return Airport[]
     */
    private function departureHubs(City $originCity, ?Airport $selectedOrigin): array
    {
        $country = $originCity->getCountry();
        if ($country === null) {
            return [];
        }

        $builder = $this->airportRepository->createQueryBuilder('airport')
            ->innerJoin('airport.city', 'city')
            ->addSelect('city')
            ->andWhere('city.country = :country')
            ->andWhere('airport.active = true')
            ->andWhere('airport.iataCode IS NOT NULL')
            ->setParameter('country', $country)
            ->orderBy('airport.iataCode', 'ASC');

        if ($selectedOrigin instanceof Airport && $selectedOrigin->getId() !== null) {
            $builder->andWhere('airport.id != :originAirport')->setParameter('originAirport', $selectedOrigin->getId());
        }

        $coverageCityIds = $this->sourceSupportedOriginCityIds();
        $airports = $builder->getQuery()->getResult();
        usort($airports, static function (Airport $left, Airport $right) use ($coverageCityIds): int {
            $leftCovered = \in_array((int) $left->getCity()?->getId(), $coverageCityIds, true);
            $rightCovered = \in_array((int) $right->getCity()?->getId(), $coverageCityIds, true);
            if ($leftCovered !== $rightCovered) {
                return $leftCovered ? -1 : 1;
            }

            return (string) $left->getIataCode() <=> (string) $right->getIataCode();
        });

        return array_slice($airports, 0, self::MAX_DEPARTURE_HUBS);
    }

    /**
     * @return int[]
     */
    private function sourceSupportedOriginCityIds(): array
    {
        if (!$this->searchSourceRepository instanceof SearchSourceRepository) {
            return [];
        }

        $ids = [];
        foreach ([SearchSource::CAPABILITY_TOUR, SearchSource::CAPABILITY_FLIGHT] as $capability) {
            foreach ($this->searchSourceRepository->findEnabledForCapability($capability) as $source) {
                $configured = $source->getConfig()['supportedOriginCityIds'] ?? [];
                if (!\is_array($configured)) {
                    continue;
                }
                foreach ($configured as $id) {
                    if (\is_int($id) || (\is_string($id) && ctype_digit($id))) {
                        $ids[] = (int) $id;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    private function cityLabel(City $city): string
    {
        return $city->getNameFa() ?: $city->getName();
    }

    private function airportLabel(Airport $airport): string
    {
        return trim(($airport->getIataCode() ? $airport->getIataCode() . ' - ' : '') . $airport->getName());
    }
}
