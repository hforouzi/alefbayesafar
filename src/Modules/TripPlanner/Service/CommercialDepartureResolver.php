<?php

namespace App\Modules\TripPlanner\Service;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Repository\AirportRepository;

/**
 * Resolves a bounded, dynamically-discovered set of alternative commercial
 * departure cities for a user's origin — used only as a fallback when a
 * direct-origin search produced no usable commercial result.
 *
 * This service ONLY discovers geographically plausible candidates. It never
 * runs a search itself and never hardcodes a specific city (e.g. Tehran):
 * the caller is responsible for actually attempting a real search against
 * each candidate and keeping only the ones real inventory supports.
 *
 * Ranking is: nearest airport first when both origin and candidate airports
 * have coordinates, otherwise alphabetical (a real geographic ranking is
 * preferred, but a deterministic fallback is used rather than pretending an
 * order that isn't backed by data).
 */
final readonly class CommercialDepartureResolver
{
    private const MAX_CANDIDATES = 3;
    private const CANDIDATE_POOL_SIZE = 30;

    public function __construct(private AirportRepository $airportRepository)
    {
    }

    /**
     * @return City[] bounded (max 3) list of alternative departure cities,
     *                each backed by a real active airport in the same country
     */
    public function candidates(City $origin): array
    {
        $country = $origin->getCountry();
        if (!$country instanceof Country) {
            return [];
        }

        $originAirport = $this->primaryAirport($origin);
        $pool = $this->airportRepository->findActiveByCountryExcludingCity($country, $origin, self::CANDIDATE_POOL_SIZE);
        if ($pool === []) {
            return [];
        }

        usort($pool, fn (Airport $a, Airport $b): int => $this->compare($originAirport, $a, $b));

        $cities = [];
        foreach ($pool as $airport) {
            $city = $airport->getCity();
            if (!$city instanceof City || isset($cities[$city->getId()])) {
                continue;
            }
            $cities[$city->getId()] = $city;
            if (\count($cities) >= self::MAX_CANDIDATES) {
                break;
            }
        }

        return array_values($cities);
    }

    /**
     * The real active airport this resolver would use for a candidate city,
     * so the caller can search it without re-deriving the same lookup.
     */
    public function primaryAirport(City $city): ?Airport
    {
        foreach ($city->getAirports() as $airport) {
            if ($airport instanceof Airport && $airport->isActive()) {
                return $airport;
            }
        }

        return null;
    }

    private function compare(?Airport $origin, Airport $a, Airport $b): int
    {
        $distanceA = $this->distanceKm($origin, $a);
        $distanceB = $this->distanceKm($origin, $b);

        if ($distanceA !== null && $distanceB !== null && $distanceA !== $distanceB) {
            return $distanceA <=> $distanceB;
        }
        if ($distanceA !== null && $distanceB === null) {
            return -1;
        }
        if ($distanceA === null && $distanceB !== null) {
            return 1;
        }

        return strcmp($a->getCity()?->getName() ?? '', $b->getCity()?->getName() ?? '');
    }

    private function distanceKm(?Airport $origin, Airport $candidate): ?float
    {
        if (!$origin instanceof Airport) {
            return null;
        }
        $lat1 = $origin->getLatitude();
        $lon1 = $origin->getLongitude();
        $lat2 = $candidate->getLatitude();
        $lon2 = $candidate->getLongitude();
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
            return null;
        }

        $earthRadiusKm = 6371.0;
        $dLat = deg2rad((float) $lat2 - (float) $lat1);
        $dLon = deg2rad((float) $lon2 - (float) $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad((float) $lat1)) * cos(deg2rad((float) $lat2)) * sin($dLon / 2) ** 2;

        return $earthRadiusKm * 2 * asin(min(1.0, sqrt($a)));
    }
}
