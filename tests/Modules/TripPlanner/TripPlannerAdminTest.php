<?php

namespace App\Tests\Modules\TripPlanner;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\Tour\Entity\ExternalTourOffer;
use App\Modules\Tour\Enum\TourAvailabilityStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

class TripPlannerAdminTest extends WebTestCase
{
    public function testTripPlannerTestRouteExistsAndRendersDebugForm(): void
    {
        $client = self::createClient();
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);
        self::assertNotNull($router->getRouteCollection()->get('trip_planner_test'));

        $client->request('GET', '/admin/trip-planner/test/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Trip Planner Test');
        self::assertSelectorExists('form[data-turbo="false"]');
        self::assertSelectorTextContains('body', 'Date mode');
        self::assertSelectorTextContains('body', 'Flexible window');
        self::assertSelectorTextContains('body', 'Run planner test');
    }

    public function testFlexibleTripPlannerFormRendersVisibleMatchingOffer(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        [$originAirport, $destinationCity] = $this->catalog($em);
        $source = (new SearchSource())
            ->setName('Trip Planner Admin Source ' . self::suffix())
            ->setDomain('example.test')
            ->setProvider('test')
            ->setProviderType(SearchSourceProviderType::MANUAL)
            ->setCapabilities([SearchSource::CAPABILITY_TOUR])
            ->setEnabled(true);
        $offer = (new ExternalTourOffer())
            ->setSearchSource($source)
            ->setProviderCode('test')
            ->setTitle('Flexible UI Test Offer ' . self::suffix())
            ->setOriginAirport($originAirport)
            ->setOriginText((string) $originAirport->getCity()?->getName())
            ->setDestinationCity($destinationCity)
            ->setDestinationText($destinationCity->getName())
            ->setDepartureDate(new \DateTimeImmutable('2026-09-25'))
            ->setReturnDate(new \DateTimeImmutable('2026-09-30'))
            ->setNights(5)
            ->setAdults(2)
            ->setChildren(0)
            ->setInfants(0)
            ->setCurrency('EUR')
            ->setTotalPrice('690.00')
            ->setAvailabilityStatus(TourAvailabilityStatus::AVAILABLE)
            ->setFetchedAt(new \DateTimeImmutable('-10 minutes'))
            ->setExpiresAt(new \DateTimeImmutable('+6 hours'))
            ->setMetadata([]);
        $em->persist($source);
        $em->persist($offer);
        $em->flush();

        $crawler = $client->request('GET', '/admin/trip-planner/test/');
        $form = $crawler->selectButton('Run planner test')->form([
            'trip_planner_test[originAirportId]' => (string) $originAirport->getId(),
            'trip_planner_test[destinationCountryId]' => (string) $destinationCity->getCountry()?->getId(),
            'trip_planner_test[destinationCityId]' => (string) $destinationCity->getId(),
            'trip_planner_test[dateMode]' => 'flexible',
            'trip_planner_test[windowStart]' => '2026-09-23',
            'trip_planner_test[windowEnd]' => '2026-10-22',
            'trip_planner_test[nights]' => '5',
            'trip_planner_test[adults]' => '2',
            'trip_planner_test[children]' => '0',
            'trip_planner_test[infants]' => '0',
            'trip_planner_test[preferredCurrency]' => 'EUR',
            'trip_planner_test[flightCabin]' => '0',
        ]);
        $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#trip-planner-summary', 'Options found');
        self::assertSelectorTextContains('body', 'Flexible UI Test Offer');
        self::assertSelectorTextContains('body', '2026-09-25 -> 2026-09-30');
    }

    public function testCountryOnlyTripPlannerFormRendersVisibleRecommendation(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        [$originAirport, $destinationCity] = $this->catalog($em);
        $source = (new SearchSource())
            ->setName('Trip Planner Country Admin Source ' . self::suffix())
            ->setDomain('example.test')
            ->setProvider('test')
            ->setProviderType(SearchSourceProviderType::MANUAL)
            ->setCapabilities([SearchSource::CAPABILITY_TOUR])
            ->setEnabled(true);
        $offer = (new ExternalTourOffer())
            ->setSearchSource($source)
            ->setProviderCode('test')
            ->setTitle('Country UI Test Offer ' . self::suffix())
            ->setOriginAirport($originAirport)
            ->setOriginText((string) $originAirport->getCity()?->getName())
            ->setDestinationCity($destinationCity)
            ->setDestinationText($destinationCity->getName())
            ->setDepartureDate(new \DateTimeImmutable('2026-09-25'))
            ->setReturnDate(new \DateTimeImmutable('2026-09-30'))
            ->setNights(5)
            ->setAdults(2)
            ->setChildren(0)
            ->setInfants(0)
            ->setHotelName('Country UI Hotel')
            ->setCurrency('EUR')
            ->setTotalPrice('690.00')
            ->setAvailabilityStatus(TourAvailabilityStatus::AVAILABLE)
            ->setFetchedAt(new \DateTimeImmutable('-10 minutes'))
            ->setExpiresAt(new \DateTimeImmutable('+6 hours'))
            ->setMetadata(['hotelStars' => 4]);
        $em->persist($source);
        $em->persist($offer);
        $em->flush();

        $crawler = $client->request('GET', '/admin/trip-planner/test/');
        $form = $crawler->selectButton('Run planner test')->form([
            'trip_planner_test[originAirportId]' => (string) $originAirport->getId(),
            'trip_planner_test[destinationCountryId]' => (string) $destinationCity->getCountry()?->getId(),
            'trip_planner_test[destinationCityId]' => '',
            'trip_planner_test[dateMode]' => 'flexible',
            'trip_planner_test[windowStart]' => '2026-09-23',
            'trip_planner_test[windowEnd]' => '2026-10-22',
            'trip_planner_test[nights]' => '5',
            'trip_planner_test[adults]' => '2',
            'trip_planner_test[children]' => '0',
            'trip_planner_test[infants]' => '0',
            'trip_planner_test[preferredCurrency]' => 'EUR',
            'trip_planner_test[flightCabin]' => '0',
        ]);
        $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#trip-planner-summary', 'Options found');
        self::assertSelectorTextContains('body', 'Country UI Test Offer');
        self::assertSelectorTextContains('body', 'Country UI Hotel');
        self::assertSelectorTextContains('body', 'Canonical: UNMATCHED');
        self::assertSelectorTextContains('body', 'No enabled hotel SearchSource is configured for independent hotel context.');
    }

    /**
     * @return array{0: Airport, 1: City}
     */
    private function catalog(EntityManagerInterface $em): array
    {
        $suffix = self::suffix();
        $country = (new Country())->setName('Trip Admin Country ' . $suffix);
        $originCity = (new City())->setCountry($country)->setName('Trip Admin Origin ' . $suffix)->setSlug('trip-admin-origin-' . strtolower($suffix));
        $destinationCity = (new City())->setCountry($country)->setName('Trip Admin Destination ' . $suffix)->setSlug('trip-admin-destination-' . strtolower($suffix));
        $originAirport = (new Airport())->setCity($originCity)->setName('Trip Admin Airport ' . $suffix)->setIataCode(substr($suffix, 0, 3));

        foreach ([$country, $originCity, $destinationCity, $originAirport] as $entity) {
            $em->persist($entity);
        }

        return [$originAirport, $destinationCity];
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
