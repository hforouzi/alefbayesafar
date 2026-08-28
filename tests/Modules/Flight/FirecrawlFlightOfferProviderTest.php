<?php

namespace App\Tests\Modules\Flight;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Repository\AirportRepository;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\Flight\Enum\FlightOfferSearchStatus;
use App\Modules\Flight\Provider\FirecrawlFlightOfferProvider;
use App\Modules\Flight\ValueObject\FlightOfferSearchRequest;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\SearchSource\Provider\FirecrawlClient;
use App\Modules\SearchSource\Provider\FirecrawlProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class FirecrawlFlightOfferProviderTest extends KernelTestCase
{
    public function testAcceptsStrictNormalizedConnectionOffer(): void
    {
        self::bootKernel();
        $this->airport('CGN');
        $this->airport('FRA');
        $this->airport('IST');
        $captured = [];
        $provider = $this->provider([new MockResponse(json_encode([
            'success' => true,
            'data' => [
                'json' => [
                    'offers' => [[
                        'externalOfferId' => 'ext-1',
                        'tripType' => 'round_trip',
                        'cabinClass' => 'economy',
                        'totalPrice' => '690.00',
                        'currency' => 'EUR',
                        'adults' => 2,
                        'children' => 1,
                        'infants' => 0,
                        'baggage' => '20 kg checked',
                        'outbound' => [
                            ['airlineName' => 'Lufthansa', 'airlineIata' => 'LH', 'originIata' => 'CGN', 'destinationIata' => 'FRA', 'departureAt' => '2026-09-10T08:00:00+02:00', 'arrivalAt' => '2026-09-10T09:00:00+02:00'],
                            ['airlineName' => 'Turkish Airlines', 'airlineIata' => 'TK', 'originIata' => 'FRA', 'destinationIata' => 'IST', 'departureAt' => '2026-09-10T10:00:00+02:00', 'arrivalAt' => '2026-09-10T14:00:00+03:00'],
                        ],
                        'inbound' => [
                            ['airlineName' => 'Turkish Airlines', 'airlineIata' => 'TK', 'originIata' => 'IST', 'destinationIata' => 'CGN', 'departureAt' => '2026-09-17T15:00:00+03:00', 'arrivalAt' => '2026-09-17T18:00:00+02:00'],
                        ],
                    ]],
                ],
            ],
        ], JSON_THROW_ON_ERROR))], $captured);

        $result = $provider->search($this->source(), new FlightOfferSearchRequest($this->airport('CGN'), $this->airport('IST'), new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-17'), 2, 1, 0, FlightCabinClass::ECONOMY));

        self::assertSame(FlightOfferSearchStatus::OFFERS_FOUND, $result->status);
        self::assertCount(1, $result->candidates);
        self::assertCount(2, $result->candidates[0]->outboundLegs);
        self::assertSame('/v2/scrape', parse_url($captured[0]['url'], PHP_URL_PATH));
        self::assertSame('json', $captured[0]['body']['formats'][0]['type']);
    }

    public function testRejectsUnknownAirportAmbiguousPriceAndMissingInbound(): void
    {
        self::bootKernel();
        $this->airport('CGN');
        $this->airport('IST');
        $provider = $this->provider([new MockResponse(json_encode([
            'success' => true,
            'data' => [
                'json' => [
                    'offers' => [
                        ['totalPrice' => '1,200.00', 'currency' => 'EUR', 'outbound' => [['originIata' => 'CGN', 'destinationIata' => 'IST', 'departureAt' => '2026-09-10T10:00:00+02:00', 'arrivalAt' => '2026-09-10T14:00:00+03:00']]],
                        ['totalPrice' => '690.00', 'currency' => 'EUR', 'outbound' => [['originIata' => 'CGN', 'destinationIata' => 'XXX', 'departureAt' => '2026-09-10T10:00:00+02:00', 'arrivalAt' => '2026-09-10T14:00:00+03:00']]],
                        ['totalPrice' => '690.00', 'currency' => 'EUR', 'tripType' => 'round_trip', 'outbound' => [['originIata' => 'CGN', 'destinationIata' => 'IST', 'departureAt' => '2026-09-10T10:00:00+02:00', 'arrivalAt' => '2026-09-10T14:00:00+03:00']]],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR))]);

        $result = $provider->search($this->source(), new FlightOfferSearchRequest($this->airport('CGN'), $this->airport('IST'), new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-17'), 2, 0, 0, FlightCabinClass::ECONOMY));

        self::assertSame(FlightOfferSearchStatus::NO_DATA, $result->status);
        self::assertSame(3, $result->metadata['rejectedCandidateCount']);
    }

    public function testProviderErrorAndNoTemplateStatuses(): void
    {
        self::bootKernel();
        $request = new FlightOfferSearchRequest($this->airport('CGN'), $this->airport('IST'), new \DateTimeImmutable('2026-09-10'), null, 1, 0, 0, FlightCabinClass::ECONOMY);
        $provider = $this->provider([new MockResponse('{"error":"no"}', ['http_code' => 500])]);

        self::assertSame(FlightOfferSearchStatus::NO_DATA, $provider->search((new SearchSource())->setName('No Template')->setDomain('example.test')->setProvider('firecrawl')->setProviderType(SearchSourceProviderType::FIRECRAWL)->setCapabilities([SearchSource::CAPABILITY_FLIGHT]), $request)->status);
        self::assertSame(FlightOfferSearchStatus::PROVIDER_ERROR, $provider->search($this->source(), $request)->status);
    }

    private function provider(array $responses, array &$captured = []): FirecrawlFlightOfferProvider
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses, &$captured): MockResponse {
            $body = $options['json'] ?? null;
            if ($body === null && \is_string($options['body'] ?? null)) {
                $body = json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR);
            }
            $captured[] = ['method' => $method, 'url' => $url, 'body' => \is_array($body) ? $body : []];

            return array_shift($responses) ?? new MockResponse('{"success":true,"data":{"json":{"offers":[]}}}');
        }, 'https://firecrawl.test');

        return new FirecrawlFlightOfferProvider(new FirecrawlProvider(new FirecrawlClient($httpClient, 'key', 'https://firecrawl.test')), self::getContainer()->get(AirportRepository::class));
    }

    private function source(): SearchSource
    {
        return (new SearchSource())
            ->setName('Flight Source')
            ->setDomain('example.test')
            ->setProvider('firecrawl')
            ->setProviderType(SearchSourceProviderType::FIRECRAWL)
            ->setCapabilities([SearchSource::CAPABILITY_FLIGHT])
            ->setConfig(['flightSearchUrlTemplate' => 'https://example.test/flights?from={origin}&to={destination}&date={departure}&return={return}&adults={adults}&children={children}&infants={infants}&cabin={cabin}']);
    }

    private function airport(string $iata): Airport
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $existing = $em->getRepository(Airport::class)->findOneBy(['iataCode' => $iata]);
        if ($existing instanceof Airport) {
            return $existing;
        }

        $country = (new Country())->setName('Flight Provider Test ' . $iata . random_int(1000, 9999));
        $city = (new City())->setCountry($country)->setName('City ' . $iata . random_int(1000, 9999));
        $airport = (new Airport())->setCity($city)->setName('Airport ' . $iata)->setIataCode($iata);
        foreach ([$country, $city, $airport] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        return $airport;
    }
}
