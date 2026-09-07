<?php

namespace App\Tests\Modules\TripPlanner;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\TripPlanner\Service\CommercialDepartureResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Covers TEST 4 from the Phase-11 planning-orchestration correction: the
 * alternative departure resolver must be dynamic (real DB-driven geography),
 * bounded, and must never surface a random/unsupported airport just because
 * it technically exists in the same country.
 */
class CommercialDepartureResolverTest extends KernelTestCase
{
    public function testCandidatesAreBoundedToThreeAndExcludeTheOriginCityItself(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $suffix = self::suffix();
        $country = (new Country())->setName('Departure Test Country ' . $suffix);

        $origin = $this->cityWithAirport($em, $country, 'Origin ' . $suffix, 35.0, 51.0);
        $others = [];
        for ($i = 0; $i < 6; ++$i) {
            $others[] = $this->cityWithAirport($em, $country, 'Alt ' . $i . ' ' . $suffix, 35.0 + $i, 51.0 + $i);
        }
        $em->flush();

        $resolver = self::getContainer()->get(CommercialDepartureResolver::class);
        $candidates = $resolver->candidates($origin);

        self::assertLessThanOrEqual(3, \count($candidates));
        foreach ($candidates as $candidate) {
            self::assertNotSame($origin->getId(), $candidate->getId(), 'the origin city itself must never be offered as its own alternative');
        }
    }

    public function testNearestAirportsByCoordinatesAreRankedFirst(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $suffix = self::suffix();
        $country = (new Country())->setName('Departure Geo Country ' . $suffix);

        // Origin at (35, 51). "Near" is ~1 degree away, "Far" is ~40 degrees away.
        $origin = $this->cityWithAirport($em, $country, 'GeoOrigin ' . $suffix, 35.0, 51.0);
        $near = $this->cityWithAirport($em, $country, 'GeoNear ' . $suffix, 36.0, 52.0);
        $far = $this->cityWithAirport($em, $country, 'GeoFar ' . $suffix, 75.0, 51.0);
        $em->flush();
        // Force Doctrine to lazily reload $origin's airport collection from the
        // database (as a freshly-repository-fetched entity would in production)
        // instead of keeping the empty in-memory ArrayCollection from construction.
        $em->refresh($origin);

        $resolver = self::getContainer()->get(CommercialDepartureResolver::class);
        $candidates = $resolver->candidates($origin);

        $names = array_map(static fn (City $city): string => $city->getName(), $candidates);
        self::assertSame([$near->getName(), $far->getName()], $names, 'the geographically nearer candidate must rank before the farther one');
    }

    public function testCitiesWithoutAnyAirportAreNeverOffered(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $suffix = self::suffix();
        $country = (new Country())->setName('Departure NoAirport Country ' . $suffix);

        $origin = $this->cityWithAirport($em, $country, 'NoAirportOrigin ' . $suffix, 35.0, 51.0);
        $airportless = (new City())->setCountry($country)->setName('Airportless ' . $suffix)->setSlug('airportless-' . strtolower($suffix));
        $em->persist($airportless);
        $em->flush();

        $resolver = self::getContainer()->get(CommercialDepartureResolver::class);
        $candidates = $resolver->candidates($origin);

        foreach ($candidates as $candidate) {
            self::assertNotSame($airportless->getId(), $candidate->getId(), 'a city with no real airport must never be offered as a departure candidate');
        }
    }

    public function testInactiveAirportsAndCitiesAreExcluded(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $suffix = self::suffix();
        $country = (new Country())->setName('Departure Inactive Country ' . $suffix);

        $origin = $this->cityWithAirport($em, $country, 'InactiveOrigin ' . $suffix, 35.0, 51.0);
        $inactiveCity = $this->cityWithAirport($em, $country, 'InactiveCity ' . $suffix, 36.0, 52.0);
        $inactiveCity->setActive(false);
        $inactiveAirportCity = (new City())->setCountry($country)->setName('InactiveAirportCity ' . $suffix)->setSlug('inactive-airport-city-' . strtolower($suffix));
        $inactiveAirport = (new Airport())->setCity($inactiveAirportCity)->setName('Inactive Airport ' . $suffix)->setActive(false);
        $em->persist($inactiveAirportCity);
        $em->persist($inactiveAirport);
        $em->flush();

        $resolver = self::getContainer()->get(CommercialDepartureResolver::class);
        $candidates = $resolver->candidates($origin);

        $ids = array_map(static fn (City $city): ?int => $city->getId(), $candidates);
        self::assertNotContains($inactiveCity->getId(), $ids);
        self::assertNotContains($inactiveAirportCity->getId(), $ids);
    }

    private function cityWithAirport(EntityManagerInterface $em, Country $country, string $name, float $lat, float $lon): City
    {
        $city = (new City())->setCountry($country)->setName($name)->setSlug($this->slug($name));
        $airport = (new Airport())->setCity($city)->setName($name . ' Airport')->setLatitude($lat)->setLongitude($lon);
        $em->persist($country);
        $em->persist($city);
        $em->persist($airport);

        return $city;
    }

    private function slug(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? $name);
    }

    private static function suffix(): string
    {
        $letters = '';
        for ($index = 0; $index < 8; ++$index) {
            $letters .= chr(random_int(65, 90));
        }

        return $letters;
    }
}
