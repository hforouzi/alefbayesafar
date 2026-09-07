<?php

namespace App\Tests\Modules\TripPlanner;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\TripPlanner\Enum\TripDateMode;
use App\Modules\TripPlanner\Enum\TripPlanningGoal;
use App\Modules\TripPlanner\ValueObject\TripSearchRequest;
use PHPUnit\Framework\TestCase;

/**
 * The user's real origin must never be silently overwritten by an
 * alternative commercial departure — only a separate field is attached.
 */
class TripSearchRequestOriginPreservationTest extends TestCase
{
    public function testCommercialDepartureAirportNeverReplacesTheUsersOwnOrigin(): void
    {
        $country = (new Country())->setName('Origin Preservation Country');
        $userOriginCity = (new City())->setCountry($country)->setName('Rasht')->setSlug('rasht');
        $userOriginAirport = (new Airport())->setCity($userOriginCity)->setName('Rasht Airport')->setIataCode('RAS');
        $altCity = (new City())->setCountry($country)->setName('Tehran')->setSlug('tehran');
        $altAirport = (new Airport())->setCity($altCity)->setName('Tehran Airport')->setIataCode('THR');

        $request = new TripSearchRequest(
            originAirport: $userOriginAirport,
            originCity: $userOriginCity,
            destinationCity: null,
            departureDate: null,
            returnDate: null,
            nights: 5,
            adults: 2,
            dateMode: TripDateMode::FLEXIBLE,
            windowStart: new \DateTimeImmutable('2026-10-01'),
            windowEnd: new \DateTimeImmutable('2026-10-10'),
            goal: TripPlanningGoal::SPECIFIC_DESTINATION,
        );

        $withFallback = $request->withCommercialDepartureAirport($altAirport);

        self::assertSame($userOriginAirport, $withFallback->originAirport, 'the user-stated origin airport must be unchanged');
        self::assertSame($userOriginCity, $withFallback->originCity, 'the user-stated origin city must be unchanged');
        self::assertSame($altAirport, $withFallback->commercialDepartureAirport, 'the alternative departure must be attached separately');
        self::assertNull($request->commercialDepartureAirport, 'the original request instance must be untouched (immutability)');
    }

    public function testWithDestinationCityKeepsCountryAndDoesNotTouchOrigin(): void
    {
        $country = (new Country())->setName('Destination Resolve Country');
        $userOriginCity = (new City())->setCountry($country)->setName('Rasht')->setSlug('rasht-2');
        $userOriginAirport = (new Airport())->setCity($userOriginCity)->setName('Rasht Airport')->setIataCode('RS2');
        $resolvedCity = (new City())->setCountry($country)->setName('Istanbul')->setSlug('istanbul');

        $request = new TripSearchRequest(
            originAirport: $userOriginAirport,
            originCity: $userOriginCity,
            destinationCity: null,
            departureDate: null,
            returnDate: null,
            nights: 5,
            adults: 2,
            dateMode: TripDateMode::FLEXIBLE,
            windowStart: new \DateTimeImmutable('2026-10-01'),
            windowEnd: new \DateTimeImmutable('2026-10-10'),
            goal: TripPlanningGoal::SPECIFIC_DESTINATION,
            destinationCountry: $country,
        );

        $resolved = $request->withDestinationCity($resolvedCity);

        self::assertSame($resolvedCity, $resolved->destinationCity);
        self::assertSame($country, $resolved->destinationCountry);
        self::assertSame($userOriginCity, $resolved->originCity, 'resolving a country-only destination must never touch the origin');
        self::assertNull($request->destinationCity, 'the original request instance must be untouched (immutability)');
    }
}
