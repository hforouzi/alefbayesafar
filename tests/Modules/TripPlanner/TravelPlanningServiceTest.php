<?php

namespace App\Tests\Modules\TripPlanner;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\SearchSource\Provider\FirecrawlClient;
use App\Modules\TripPlanner\Enum\TripDateMode;
use App\Modules\TripPlanner\Enum\TripPlanningGoal;
use App\Modules\TripPlanner\Service\TravelPlanningService;
use App\Modules\TripPlanner\ValueObject\TripSearchRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Regression coverage for the Phase-11 planning-orchestration correction:
 * OWN data first, then LIVE external search across Tour/Flight/Hotel run
 * independently (Tour returning zero must never gate Flight/Hotel), direct
 * origin is always tried before any alternative departure fallback, and
 * country-only destinations discover a bounded set of real airport cities
 * instead of skipping external search entirely.
 */
class TravelPlanningServiceTest extends KernelTestCase
{
    public function testFlightSearchExecutesIndependentlyWhenTourCandidatesAreZero(): void
    {
        self::bootKernel();
        $em = self::em();
        [$originCity, $originAirport, $destinationCity, $destinationAirport] = $this->catalog($em);
        // Deliberately no Tour SearchSource is configured — Tour must return zero.
        $this->flightSource($em, $originAirport->getIataCode(), $destinationAirport->getIataCode(), '2026-10-01', '2026-10-06');
        $em->flush();

        $result = $this->plan($originAirport, $originCity, $destinationAirport, $destinationCity);

        $diagnostics = $result->diagnostics;
        self::assertSame(0, $diagnostics['liveSearch']['tour']['acceptedCount'] ?? null, 'this fixture has no tour source and must genuinely find zero tour candidates');
        self::assertArrayHasKey('direct', $diagnostics['liveSearch']['flight'], 'flight search must have been attempted even though tour found nothing');
        self::assertGreaterThan(0, $diagnostics['liveSearch']['flight']['direct']['acceptedTotal'], 'the mocked live flight offer must have been accepted independently of tour');
    }

    public function testHotelSearchExecutesIndependentlyWhenTourCandidatesAreZero(): void
    {
        self::bootKernel();
        $em = self::em();
        [$originCity, $originAirport, $destinationCity] = $this->catalog($em);
        $hotel = (new Hotel())->setName('Independent Hotel ' . self::suffix())->setSlug('independent-hotel-' . strtolower(self::suffix()))->setCity($destinationCity)->setActive(true);
        $em->persist($hotel);
        // No Tour source and no Hotel SearchSource: hotel own/live channels find nothing,
        // but the search must still be ATTEMPTED (proven via diagnostics) rather than skipped.
        $em->flush();

        $result = $this->plan($originAirport, $originCity, $this->firstAirport($destinationCity), $destinationCity);

        $diagnostics = $result->diagnostics;
        self::assertSame(0, $diagnostics['liveSearch']['tour']['acceptedCount'] ?? null);
        self::assertArrayHasKey('searches', $diagnostics['liveSearch']['hotel'], 'hotel search must have run (produced search diagnostics) rather than being skipped because tour found nothing');
        self::assertNotSame([], $diagnostics['liveSearch']['hotel']['searches']);
    }

    public function testDirectOriginResultPreventsUnnecessaryAlternativeDepartureFallback(): void
    {
        self::bootKernel();
        $em = self::em();
        [$originCity, $originAirport, $destinationCity, $destinationAirport] = $this->catalog($em);
        $this->flightSource($em, $originAirport->getIataCode(), $destinationAirport->getIataCode(), '2026-10-01', '2026-10-06');
        $em->flush();

        $result = $this->plan($originAirport, $originCity, $destinationAirport, $destinationCity);

        $flightDiagnostics = $result->diagnostics['liveSearch']['flight'];
        self::assertGreaterThan(0, $flightDiagnostics['direct']['acceptedTotal']);
        self::assertFalse($flightDiagnostics['fallback']['attempted'], 'a successful direct-origin result must prevent the alternative departure fallback from running at all');
    }

    public function testAlternativeDepartureFallbackIsAttemptedAndUsesDynamicCandidateWhenDirectFindsNothing(): void
    {
        self::bootKernel();
        $em = self::em();
        [$originCity, $originAirport, $destinationCity, $destinationAirport] = $this->catalog($em);
        $altCity = (new City())->setCountry($originCity->getCountry())->setName('AltDeparture ' . self::suffix())->setSlug('alt-departure-' . strtolower(self::suffix()));
        $altAirport = (new Airport())->setCity($altCity)->setName('Alt Airport ' . self::suffix())->setIataCode($this->uniqueIata($em))->setLatitude((string) $originAirport->getLatitude())->setLongitude((string) $originAirport->getLongitude());
        $em->persist($altCity);
        $em->persist($altAirport);

        // The mocked provider only returns a real offer when the ALTERNATE airport is the origin.
        $this->flightSourceRespondingOnlyForOrigin($em, $altAirport->getIataCode(), $destinationAirport->getIataCode(), '2026-10-01', '2026-10-06');
        $em->flush();
        // Freshly-persisted-in-this-process entities stay in Doctrine's identity map
        // with their original, never-hydrated collections. Refresh so getAirports()
        // lazily reloads from the database, matching how a real request would see
        // these entities (always fetched via a repository query, never freshly
        // constructed mid-request).
        $em->refresh($originCity);
        $em->refresh($altCity);

        $result = $this->plan($originAirport, $originCity, $destinationAirport, $destinationCity);

        $flightDiagnostics = $result->diagnostics['liveSearch']['flight'];
        self::assertSame(0, $flightDiagnostics['direct']['acceptedTotal'], 'the direct origin must genuinely find nothing in this fixture');
        self::assertTrue($flightDiagnostics['fallback']['attempted'], 'fallback must be attempted once direct produced nothing');
        self::assertSame($altCity->getName(), $flightDiagnostics['fallback']['resolvedCity'] ?? null, 'the dynamically discovered alternate city must be the one that produced a real result');
        self::assertLessThanOrEqual(3, \count($flightDiagnostics['fallback']['candidates']), 'fallback candidates must be bounded');
    }

    public function testCountryOnlyDestinationDiscoversBoundedRealAirportCities(): void
    {
        self::bootKernel();
        $em = self::em();
        [$originCity, $originAirport] = $this->catalog($em);
        $country = (new Country())->setName('Country Discovery ' . self::suffix());
        for ($i = 0; $i < 7; ++$i) {
            $city = (new City())->setCountry($country)->setName('DiscoveryCity ' . $i . ' ' . self::suffix())->setSlug('discovery-city-' . $i . '-' . strtolower(self::suffix()));
            $airport = (new Airport())->setCity($city)->setName('Discovery Airport ' . $i)->setIataCode($this->uniqueIata($em));
            $em->persist($country);
            $em->persist($city);
            $em->persist($airport);
        }
        $em->flush();

        $service = self::getContainer()->get(TravelPlanningService::class);
        $request = new TripSearchRequest(
            originAirport: $originAirport,
            originCity: $originCity,
            destinationCity: null,
            departureDate: null,
            returnDate: null,
            nights: 5,
            adults: 2,
            preferredCurrency: 'EUR',
            dateMode: TripDateMode::FLEXIBLE,
            windowStart: new \DateTimeImmutable('2026-10-01'),
            windowEnd: new \DateTimeImmutable('2026-10-06'),
            goal: TripPlanningGoal::SPECIFIC_DESTINATION,
            destinationCountry: $country,
        );

        $result = $service->plan($request);

        $flightDiscovery = $result->diagnostics['liveSearch']['flight']['countryDiscovery'] ?? null;
        self::assertNotNull($flightDiscovery, 'flight search must not be skipped merely because destinationCity is null');
        self::assertLessThanOrEqual(5, \count($flightDiscovery['cities']));
        self::assertNotSame([], $flightDiscovery['cities']);
    }

    public function testProviderFailureIsDistinguishableFromSuccessfulEmptyResult(): void
    {
        self::bootKernel();
        $em = self::em();
        [$originCity, $originAirport, $destinationCity, $destinationAirport] = $this->catalog($em);
        $source = (new SearchSource())
            ->setName('Failing Flight Source ' . self::suffix())
            ->setDomain('example.test')
            ->setProvider('firecrawl')
            ->setProviderType(SearchSourceProviderType::FIRECRAWL)
            ->setEnabled(true)
            ->setCapabilities([SearchSource::CAPABILITY_FLIGHT])
            ->setConfig(['flightSearchUrlTemplate' => 'https://example.test/flights?from={origin}&to={destination}&date={departure}&return={return}&adults={adults}&children={children}&infants={infants}&cabin={cabin}']);
        $em->persist($source);
        $em->flush();

        self::getContainer()->set(FirecrawlClient::class, new FirecrawlClient(new MockHttpClient(new MockResponse('{"error":"boom"}', ['http_code' => 500])), 'key', 'https://firecrawl.test'));

        $result = $this->plan($originAirport, $originCity, $destinationAirport, $destinationCity);

        $searches = $result->diagnostics['liveSearch']['flight']['direct']['destinations'][$destinationCity->getName()]['searches'] ?? [];
        self::assertNotSame([], $searches);
        $sources = $searches[0]['sources'] ?? [];
        self::assertNotSame([], $sources, 'a provider failure must still be reported, not silently treated as an empty successful search');
        self::assertSame('provider_error', $sources[0]['status'] ?? null);
    }

    public function testOwnFlightInventoryIsNotDisplacedByLiveSearchOrchestrationChanges(): void
    {
        self::bootKernel();
        $em = self::em();
        [$originCity, $originAirport, $destinationCity, $destinationAirport] = $this->catalog($em);
        // No SearchSource configured at all: only own inventory (none persisted here either)
        // is available. This proves the orchestration change does not require any provider
        // to be configured for the request to complete without throwing / without fabricating data.
        $em->flush();

        $result = $this->plan($originAirport, $originCity, $destinationAirport, $destinationCity);

        self::assertSame(0, $result->diagnostics['liveSearch']['tour']['acceptedCount'] ?? 0);
        self::assertSame(0, $result->diagnostics['liveSearch']['flight']['direct']['acceptedTotal'] ?? null);
        self::assertSame([], $result->options, 'with no own inventory and no provider configured, zero options is the honest result — nothing must be fabricated');
    }

    private function plan(Airport $originAirport, City $originCity, Airport $destinationAirport, City $destinationCity): \App\Modules\TripPlanner\ValueObject\TripPlanResult
    {
        $service = self::getContainer()->get(TravelPlanningService::class);
        self::assertInstanceOf(TravelPlanningService::class, $service);

        $request = new TripSearchRequest(
            originAirport: $originAirport,
            originCity: $originCity,
            destinationCity: $destinationCity,
            departureDate: null,
            returnDate: null,
            nights: 5,
            adults: 2,
            preferredCurrency: 'EUR',
            dateMode: TripDateMode::FLEXIBLE,
            windowStart: new \DateTimeImmutable('2026-10-01'),
            windowEnd: new \DateTimeImmutable('2026-10-06'),
            goal: TripPlanningGoal::SPECIFIC_DESTINATION,
        );

        return $service->plan($request);
    }

    /**
     * @return array{0: City, 1: Airport, 2: City, 3: Airport}
     */
    private function catalog(EntityManagerInterface $em): array
    {
        $suffix = self::suffix();
        $country = (new Country())->setName('Planning Country ' . $suffix);
        $originCity = (new City())->setCountry($country)->setName('Planning Origin ' . $suffix)->setSlug('planning-origin-' . strtolower($suffix));
        $destinationCity = (new City())->setCountry($country)->setName('Planning Destination ' . $suffix)->setSlug('planning-destination-' . strtolower($suffix));
        $originAirport = (new Airport())->setCity($originCity)->setName('Planning Origin Airport ' . $suffix)->setIataCode($this->uniqueIata($em))->setLatitude('37.0')->setLongitude('49.5');
        $destinationAirport = (new Airport())->setCity($destinationCity)->setName('Planning Destination Airport ' . $suffix)->setIataCode($this->uniqueIata($em))->setLatitude('41.0')->setLongitude('28.9');

        foreach ([$country, $originCity, $destinationCity, $originAirport, $destinationAirport] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $em->refresh($originCity);
        $em->refresh($destinationCity);

        return [$originCity, $originAirport, $destinationCity, $destinationAirport];
    }

    private function firstAirport(City $city): Airport
    {
        $airport = $city->getAirports()->first();
        self::assertInstanceOf(Airport::class, $airport);

        return $airport;
    }

    private function flightSource(EntityManagerInterface $em, string $originIata, string $destinationIata, string $departure, string $return): SearchSource
    {
        $source = (new SearchSource())
            ->setName('Flight Source ' . self::suffix())
            ->setDomain('example.test')
            ->setProvider('firecrawl')
            ->setProviderType(SearchSourceProviderType::FIRECRAWL)
            ->setEnabled(true)
            ->setCapabilities([SearchSource::CAPABILITY_FLIGHT])
            ->setConfig(['flightSearchUrlTemplate' => 'https://example.test/flights?from={origin}&to={destination}&date={departure}&return={return}&adults={adults}&children={children}&infants={infants}&cabin={cabin}']);
        $em->persist($source);

        self::getContainer()->set(FirecrawlClient::class, new FirecrawlClient(
            new MockHttpClient(fn (): MockResponse => new MockResponse($this->offerPayload($originIata, $destinationIata, $departure, $return))),
            'key',
            'https://firecrawl.test',
        ));

        return $source;
    }

    /**
     * A flight source whose mocked provider only returns a real offer when
     * the search's origin IATA matches $onlyOriginIata — simulating a real
     * direct-origin miss followed by a real alternative-departure hit.
     */
    private function flightSourceRespondingOnlyForOrigin(EntityManagerInterface $em, string $onlyOriginIata, string $destinationIata, string $departure, string $return): SearchSource
    {
        $source = (new SearchSource())
            ->setName('Selective Flight Source ' . self::suffix())
            ->setDomain('example.test')
            ->setProvider('firecrawl')
            ->setProviderType(SearchSourceProviderType::FIRECRAWL)
            ->setEnabled(true)
            ->setCapabilities([SearchSource::CAPABILITY_FLIGHT])
            ->setConfig(['flightSearchUrlTemplate' => 'https://example.test/flights?from={origin}&to={destination}&date={departure}&return={return}&adults={adults}&children={children}&infants={infants}&cabin={cabin}']);
        $em->persist($source);

        $offerPayload = $this->offerPayload($onlyOriginIata, $destinationIata, $departure, $return);
        $emptyPayload = json_encode(['success' => true, 'data' => ['json' => ['offers' => []]]], JSON_THROW_ON_ERROR);

        self::getContainer()->set(FirecrawlClient::class, new FirecrawlClient(
            new MockHttpClient(function (string $method, string $url, array $options) use ($onlyOriginIata, $offerPayload, $emptyPayload): MockResponse {
                $body = $options['json'] ?? (\is_string($options['body'] ?? null) ? json_decode($options['body'], true) : null);
                $requestedUrl = \is_array($body) ? (string) ($body['url'] ?? '') : '';

                return str_contains($requestedUrl, 'from=' . $onlyOriginIata)
                    ? new MockResponse($offerPayload)
                    : new MockResponse($emptyPayload);
            }),
            'key',
            'https://firecrawl.test',
        ));

        return $source;
    }

    private function offerPayload(string $originIata, string $destinationIata, string $departure, string $return): string
    {
        return json_encode([
            'success' => true,
            'data' => [
                'markdown' => sprintf('Test Air %s %s %s 08:00 12:00. Test Air %s %s %s 08:00 12:00. EUR 300.00.', $originIata, $destinationIata, $departure, $destinationIata, $originIata, $return),
                'json' => [
                    'offers' => [[
                        'externalOfferId' => 'flight-' . $originIata . '-' . $destinationIata,
                        'tripType' => 'round_trip',
                        'cabinClass' => 'economy',
                        'totalPrice' => '300.00',
                        'currency' => 'EUR',
                        'adults' => 2,
                        'children' => 0,
                        'infants' => 0,
                        'outbound' => [[
                            'airlineName' => 'Test Air',
                            'airlineIata' => 'TA',
                            'originIata' => $originIata,
                            'destinationIata' => $destinationIata,
                            'departureAt' => $departure . 'T08:00:00+00:00',
                            'arrivalAt' => $departure . 'T12:00:00+00:00',
                        ]],
                        'inbound' => [[
                            'airlineName' => 'Test Air',
                            'airlineIata' => 'TA',
                            'originIata' => $destinationIata,
                            'destinationIata' => $originIata,
                            'departureAt' => $return . 'T08:00:00+00:00',
                            'arrivalAt' => $return . 'T12:00:00+00:00',
                        ]],
                    ]],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    private function uniqueIata(EntityManagerInterface $em): string
    {
        do {
            $code = chr(random_int(65, 90)) . chr(random_int(65, 90)) . chr(random_int(65, 90));
        } while ($em->getRepository(Airport::class)->findOneBy(['iataCode' => $code]) instanceof Airport);

        return $code;
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
