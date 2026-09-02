<?php

namespace App\Tests\Modules\Tour;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelRoomType;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\SearchSource\Provider\FirecrawlClient;
use App\Modules\SearchSource\Provider\FirecrawlProvider;
use App\Modules\Tour\Entity\ExternalTourOffer;
use App\Modules\Tour\Entity\TourPackage;
use App\Modules\Tour\Enum\ExternalTourOfferSearchStatus;
use App\Modules\Tour\Enum\TourAvailabilityStatus;
use App\Modules\Tour\Enum\TourOfferSourceType;
use App\Modules\Tour\Enum\TourPricingMode;
use App\Modules\Tour\Provider\FirecrawlExternalTourOfferProvider;
use App\Modules\Tour\Provider\LastSecondExternalTourOfferProvider;
use App\Modules\Tour\Repository\ExternalTourOfferRepository;
use App\Modules\Tour\Repository\TourPackageRepository;
use App\Modules\Tour\Service\ExternalTourOfferSearchService;
use App\Modules\Tour\Service\ExternalTourOfferStoreService;
use App\Modules\Tour\Service\TourOfferResolver;
use App\Modules\Tour\Service\TourSourceEligibilityService;
use App\Modules\Tour\ValueObject\ExternalTourOfferCandidate;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchRequest;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchResult;
use App\Modules\Tour\ValueObject\TourMoney;
use App\Modules\Tour\ValueObject\TourPricingCandidate;
use App\Shared\Date\LocaleDateTimeFormatter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ExternalTourOfferFlowTest extends KernelTestCase
{
    public function testSearchRequestValidationAndProviderStatuses(): void
    {
        self::bootKernel();
        $data = $this->catalogData();
        $request = new ExternalTourOfferSearchRequest($data['cgn'], $data['istanbul'], new \DateTimeImmutable('2026-09-10'), null, null, null, 5, 2, 0, 0);
        $durationOnlyRequest = new ExternalTourOfferSearchRequest($data['cgn'], $data['istanbul'], null, null, null, null, 5, 2, 0, 0);
        self::assertSame(1, $request->rooms);
        self::assertSame(1, $request->context()['rooms']);
        self::assertNull($durationOnlyRequest->departureDate);
        self::assertSame(5, $durationOnlyRequest->nights);
        self::assertSame('62800000.00', TourMoney::normalize('62,800,000'));
        self::assertNull(TourMoney::normalize('1,111'));

        $source = $this->source();
        self::assertSame(ExternalTourOfferSearchStatus::NO_DATA, $this->provider([new MockResponse('{}')])->search((clone $source)->setConfig([]), $request)->status);
        self::assertSame(ExternalTourOfferSearchStatus::PROVIDER_ERROR, $this->provider([new MockResponse('{"error":"no"}', ['http_code' => 500])])->search($source, $request)->status);

        $noResults = $this->provider([new MockResponse(json_encode(['success' => true, 'data' => ['markdown' => 'No Istanbul tours found.', 'json' => ['offers' => []]]], JSON_THROW_ON_ERROR))])->search($source, $request);
        self::assertSame(ExternalTourOfferSearchStatus::NO_RESULTS, $noResults->status);

        $this->expectException(\InvalidArgumentException::class);
        new ExternalTourOfferSearchRequest($data['cgn'], $data['istanbul'], new \DateTimeImmutable('2026-09-10'), null, null, null, 5, 2, 1, 0, [], rooms: 0);
    }

    public function testSourceEligibilitySkipsProviderBeforeCallingIt(): void
    {
        self::bootKernel();
        $data = $this->catalogData();
        $source = $this->source()
            ->setName('Tehran-only Tour Source ' . self::suffix())
            ->setConfig(['supportedOriginCityIds' => [999999]]);
        $this->em()->persist($source);
        $this->em()->flush();

        $called = 0;
        $provider = new class($called, $source->getName()) implements \App\Modules\Tour\Provider\ExternalTourOfferProviderInterface {
            public function __construct(private int &$called, private readonly string $sourceName) {}
            public function supports(SearchSource $source): bool { return $source->getName() === $this->sourceName; }
            public function search(SearchSource $source, ExternalTourOfferSearchRequest $request): ExternalTourOfferSearchResult
            {
                ++$this->called;

                return ExternalTourOfferSearchResult::noResults($source);
            }
        };

        $service = new ExternalTourOfferSearchService([$provider], self::getContainer()->get(\App\Modules\SearchSource\Repository\SearchSourceRepository::class), new TourSourceEligibilityService());
        $summary = $service->search(new ExternalTourOfferSearchRequest($data['cgn'], $data['istanbul'], new \DateTimeImmutable('2026-09-10'), null, null, null, 5, 2, 0, 0));

        self::assertSame(0, $called);
        $sourceResult = null;
        foreach ($summary->getResults() as $result) {
            if ($result->source->getId() === $source->getId()) {
                $sourceResult = $result;
                break;
            }
        }

        self::assertNotNull($sourceResult);
        self::assertSame(ExternalTourOfferSearchStatus::SKIPPED->value, $sourceResult->status->value);
        self::assertSame('ineligible', $sourceResult->metadata['eligibility']);
        self::assertSame(['ORIGIN_NOT_SUPPORTED'], $sourceResult->metadata['eligibilityReasons']);
    }

    public function testFirecrawlProviderAcceptsVerifiedOfferAndRejectsUnsupportedFacts(): void
    {
        self::bootKernel();
        $data = $this->catalogData();
        $captured = [];
        $provider = $this->provider([new MockResponse(json_encode([
            'success' => true,
            'data' => [
                'markdown' => 'Istanbul 5 Nights. Destination Istanbul. Arts Hotel Istanbul Harbiye. Breakfast. Departure 2026-09-10. Return 2026-09-15. EUR 1290.00.',
                'json' => ['offers' => [[
                    'externalOfferId' => 'tour-1',
                    'title' => 'Istanbul 5 Nights',
                    'destination' => 'Istanbul',
                    'departureDate' => '2026-09-10',
                    'returnDate' => '2026-09-15',
                    'nights' => 5,
                    'hotelName' => 'Arts Hotel Istanbul Harbiye',
                    'boardType' => 'Breakfast',
                    'flightSummary' => 'Flight included',
                    'totalPrice' => '1290.00',
                    'currency' => 'EUR',
                    'inclusions' => ['flight', 'hotel', 'breakfast'],
                ]]],
            ],
        ], JSON_THROW_ON_ERROR))], $captured);

        $result = $provider->search($this->source(), new ExternalTourOfferSearchRequest($data['cgn'], $data['istanbul'], new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), null, null, 5, 2, 0, 0));

        self::assertSame(ExternalTourOfferSearchStatus::OFFERS_FOUND, $result->status);
        self::assertSame('1290.00', $result->candidates[0]->totalPrice);
        self::assertSame('breakfast', $result->candidates[0]->boardType);
        self::assertSame('/v2/scrape', parse_url($captured[0]['url'], PHP_URL_PATH));
        self::assertStringContainsString('rooms=1', $captured[0]['body']['url']);
        self::assertSame('markdown', $captured[0]['body']['formats'][0]);
        self::assertSame('json', $captured[0]['body']['formats'][1]['type']);

        $rejected = $this->provider([new MockResponse(json_encode([
            'success' => true,
            'data' => [
                'markdown' => 'Generic Istanbul tours for two adults.',
                'json' => ['offers' => [[
                    'title' => 'Istanbul 5 Nights',
                    'destination' => 'Istanbul',
                    'departureDate' => '2026-09-10',
                    'totalPrice' => '1290.00',
                    'currency' => 'EUR',
                    'hotelName' => 'Arts Hotel Istanbul Harbiye',
                ]]],
            ],
        ], JSON_THROW_ON_ERROR))])->search($this->source(), new ExternalTourOfferSearchRequest($data['cgn'], $data['istanbul'], new \DateTimeImmutable('2026-09-10'), null, null, null, 5, 2, 0, 0));

        self::assertSame(ExternalTourOfferSearchStatus::NO_DATA, $rejected->status);
        self::assertContains('UNVERIFIED_PRICE', $rejected->metadata['offerDiagnostics'][0]['reasons']);
        self::assertContains('UNVERIFIED_HOTEL', $rejected->metadata['offerDiagnostics'][0]['reasons']);

        $alibabaCaptured = [];
        $alibaba = $this->source()
            ->setDomain('alibaba.ir')
            ->setConfig([
                'tourSearchUrlTemplate' => 'https://www.alibaba.ir/tour/{originLocation}/{destinationLocation}?from={departure}&to={return}&rooms={rooms}',
                'originLocationMap' => [(string) $data['cgn']->getCity()?->getId() => 'iran-rasht'],
                'destinationLocationMap' => [(string) $data['istanbul']->getId() => 'turkey-istanbul'],
            ]);
        $this->provider([new MockResponse(json_encode(['success' => true, 'data' => ['markdown' => '', 'json' => ['offers' => []]]], JSON_THROW_ON_ERROR))], $alibabaCaptured)
            ->search($alibaba, new ExternalTourOfferSearchRequest($data['cgn'], $data['istanbul'], new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), null, null, 5, 2, 0, 0, rooms: 2));

        self::assertSame('https://www.alibaba.ir/tour/iran-rasht/turkey-istanbul?from=2026-09-10&to=2026-09-15&rooms=2', $alibabaCaptured[0]['body']['url']);
    }

    public function testLastSecondNormalizationPreservesRawPricesAndUsesMinimumComparisonPrice(): void
    {
        self::bootKernel();
        $data = $this->catalogData();
        $source = (new SearchSource())
            ->setName('LastSecond Fixture ' . self::suffix())
            ->setDomain('rapi.lastsecond.ir')
            ->setProvider('lastsecond')
            ->setProviderType(SearchSourceProviderType::API)
            ->setCapabilities([SearchSource::CAPABILITY_TOUR])
            ->setConfig([
                'lastSecondApiUrl' => 'https://rapi.lastsecond.ir/treks/index',
                'lastSecondPublicBaseUrl' => 'https://lastsecond.ir',
            ]);

        $captured = [];
        $provider = new LastSecondExternalTourOfferProvider(new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $body = $options['json'] ?? null;
            $rawBody = \is_string($options['body'] ?? null) ? $options['body'] : null;
            if ($body === null && \is_string($options['body'] ?? null)) {
                $body = json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR);
            }
            $captured[] = [
                'method' => $method,
                'url' => $url,
                'body' => \is_array($body) ? $body : [],
                'rawBody' => $rawBody,
            ];

            return new MockResponse(json_encode([
                'items' => [[
                    'id' => 123,
                    'tourTitle' => 'Istanbul Tour September',
                    'source' => ['titleEn' => 'Tehran'],
                    'locations' => [['location' => ['titleEn' => 'Istanbul']]],
                    'startDate' => '2026-09-10T00:00:00.000Z',
                    'endDate' => '2026-09-15T00:00:00.000Z',
                    'duration' => 5,
                    'agency' => ['titleEn' => 'Arshin Parvaz'],
                    'airline' => ['titleEn' => 'Soroush Airlines'],
                    'hotels' => [[
                        'hotel' => ['titleEn' => 'Arts Hotel Istanbul Harbiye', 'stars' => 4],
                        'service' => ['key' => 'BB', 'title' => 'Breakfast (BB)'],
                        'roomType' => ['title' => 'Double'],
                    ]],
                    'prices' => [
                        ['segment' => 1, 'values' => [['currency' => 1, 'price' => 75500000, 'discount' => 0]]],
                        ['segment' => 2, 'values' => [['currency' => 1, 'price' => 62800000, 'discount' => 0]]],
                    ],
                    'link' => '/tours/istanbul-fixture',
                ]],
            ], JSON_THROW_ON_ERROR));
        }), new LocaleDateTimeFormatter());

        $result = $provider->search($source, new ExternalTourOfferSearchRequest($data['cgn'], $data['istanbul'], new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), null, null, 5, 2, 0, 0));

        self::assertSame(ExternalTourOfferSearchStatus::OFFERS_FOUND, $result->status);
        self::assertSame('POST', $captured[0]['method']);
        self::assertSame('https://rapi.lastsecond.ir/treks/index', $captured[0]['url']);
        self::assertStringContainsString('"startDate":{}', (string) $captured[0]['rawBody']);
        self::assertStringContainsString('"endDate":{}', (string) $captured[0]['rawBody']);
        self::assertSame([5], $captured[0]['body']['filters']['durations']);
        self::assertSame('62800000.00', $result->candidates[0]->totalPrice);
        self::assertSame('IRR', $result->candidates[0]->currency);
        self::assertSame('Tehran', $result->candidates[0]->originText);
        self::assertSame('Soroush Airlines', $result->candidates[0]->flightSummary);
        self::assertSame('breakfast', $result->candidates[0]->boardType);
        self::assertSame('Arshin Parvaz', $result->candidates[0]->metadata['agency']);
        self::assertSame('https://lastsecond.ir/tours/istanbul-fixture', $result->candidates[0]->bookingUrl);
        self::assertSame('minimum_positive_source_price', $result->candidates[0]->metadata['comparisonPriceSource']);
        self::assertSame('Source minimum comparison price; segment semantics not inferred.', $result->candidates[0]->metadata['priceInterpretation']);
        self::assertCount(2, $result->candidates[0]->metadata['rawSegmentPrices']);
    }

    public function testStoreSnapshotTtlHotelRoomMappingAndResolverPriority(): void
    {
        self::bootKernel();
        $data = $this->catalogData();
        $request = new ExternalTourOfferSearchRequest($data['cgn'], $data['istanbul'], new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), null, null, 5, 2, 0, 0);
        $source = $this->source();
        $externalOfferId = 'tour-store-' . self::suffix();
        $this->em()->persist($source);
        $this->em()->flush();

        $candidate = new ExternalTourOfferCandidate(
            sourceIdentifier: ExternalTourOfferCandidate::sourceIdentifier($source),
            sourceName: $source->getName(),
            providerCode: 'firecrawl',
            externalOfferId: $externalOfferId,
            title: 'Istanbul 5 Nights',
            originText: 'CGN',
            destinationText: 'Istanbul',
            departureDate: new \DateTimeImmutable('2026-09-10'),
            returnDate: new \DateTimeImmutable('2026-09-15'),
            validFrom: null,
            validTo: null,
            nights: 5,
            days: 6,
            hotelName: 'Arts Hotel Istanbul Harbiye',
            roomName: 'Double Room',
            boardType: 'breakfast',
            flightSummary: 'Flight included',
            adults: 2,
            children: 0,
            infants: 0,
            childrenAges: [],
            inclusions: ['flight', 'hotel', 'breakfast'],
            exclusions: ['visa'],
            currency: 'EUR',
            totalPrice: '1290.00',
            bookingUrl: 'https://example.test/tours/istanbul',
            availabilityStatus: TourAvailabilityStatus::AVAILABLE,
        );

        $store = self::getContainer()->get(ExternalTourOfferStoreService::class);
        self::assertSame(1, $store->storeResult($request, ExternalTourOfferSearchResult::success($source, [$candidate]), new \DateTimeImmutable('2026-09-03 10:00:00')));
        self::assertSame(1, $this->em()->getRepository(ExternalTourOffer::class)->count(['searchSource' => $source]));
        self::assertSame(1, $store->storeResult($request, ExternalTourOfferSearchResult::success($source, [$candidate]), new \DateTimeImmutable('2026-09-03 10:05:00')));
        self::assertSame(1, $this->em()->getRepository(ExternalTourOffer::class)->count(['searchSource' => $source]));

        $offer = $this->em()->getRepository(ExternalTourOffer::class)->findOneBy(['externalOfferId' => $externalOfferId]);
        self::assertInstanceOf(ExternalTourOffer::class, $offer);
        self::assertSame('2026-09-03 16:05:00', $offer->getExpiresAt()?->format('Y-m-d H:i:s'));
        self::assertSame($data['hotel']->getId(), $offer->getHotel()?->getId());
        self::assertSame($data['room']->getId(), $offer->getHotelRoomType()?->getId());

        $own = $this->ownPackage($data['cgn'], $data['istanbul']);
        $resolved = $this->resolver()->resolve($data['cgn'], $data['istanbul'], new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), null, null, 5, 2, 0, 0);

        self::assertSame(TourOfferSourceType::OWN, $resolved[0]->sourceType);
        self::assertSame((int) $own->getId(), $resolved[0]->tourPackageId);
        self::assertSame('1490.00', $resolved[0]->totalPrice);
        self::assertSame(TourOfferSourceType::EXTERNAL, $resolved[1]->sourceType);

        $wrongOrigin = (new Airport())->setCity($data['cologne'])->setName('Wrong Origin ' . self::suffix())->setIataCode(self::iata());
        $this->em()->persist($wrongOrigin);
        $this->em()->flush();
        $wrongOriginResolved = $this->resolver()->resolve($wrongOrigin, $data['istanbul'], new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), null, null, 5, 2, 0, 0);
        self::assertEmpty(array_filter($wrongOriginResolved, static fn (TourPricingCandidate $candidate): bool => $candidate->sourceType === TourOfferSourceType::EXTERNAL));

        $offer->setExpiresAt(new \DateTimeImmutable('-1 hour'));
        $this->em()->flush();
        $freshOnly = $this->resolver()->resolve($data['cgn'], $data['istanbul'], new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), null, null, 5, 2, 0, 0);
        self::assertCount(1, $freshOnly);
        self::assertSame(TourOfferSourceType::OWN, $freshOnly[0]->sourceType);

        self::assertSame(0, $store->storeResult($request, ExternalTourOfferSearchResult::noData($source), new \DateTimeImmutable('2026-09-03 11:00:00')));
        self::assertSame(1, $this->em()->getRepository(ExternalTourOffer::class)->count(['searchSource' => $source]));
        self::assertSame(0, $store->storeResult($request, ExternalTourOfferSearchResult::failure($source, ['failed']), new \DateTimeImmutable('2026-09-03 11:00:00')));
        self::assertSame(1, $this->em()->getRepository(ExternalTourOffer::class)->count(['searchSource' => $source]));
        self::assertSame(0, $store->storeResult($request, ExternalTourOfferSearchResult::noResults($source), new \DateTimeImmutable('2026-09-03 11:00:00')));
        self::assertSame(0, $this->em()->getRepository(ExternalTourOffer::class)->count(['searchSource' => $source]));
    }

    private function provider(array $responses, array &$captured = []): FirecrawlExternalTourOfferProvider
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses, &$captured): MockResponse {
            $body = $options['json'] ?? null;
            if ($body === null && \is_string($options['body'] ?? null)) {
                $body = json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR);
            }
            $captured[] = ['method' => $method, 'url' => $url, 'body' => \is_array($body) ? $body : []];

            return array_shift($responses) ?? new MockResponse('{"success":true,"data":{"markdown":"","json":{"offers":[]}}}');
        }, 'https://firecrawl.test');

        return new FirecrawlExternalTourOfferProvider(new FirecrawlProvider(new FirecrawlClient($httpClient, 'key', 'https://firecrawl.test')));
    }

    private function source(): SearchSource
    {
        return (new SearchSource())
            ->setName('Tour Source ' . self::suffix())
            ->setDomain('example.test')
            ->setProvider('firecrawl')
            ->setProviderType(SearchSourceProviderType::FIRECRAWL)
            ->setCapabilities([SearchSource::CAPABILITY_TOUR])
            ->setConfig(['tourSearchUrlTemplate' => 'https://example.test/tours?destination={destinationName}&departure={departure}&return={return}&nights={nights}&adults={adults}&children={children}&infants={infants}&rooms={rooms}&currency={currency}']);
    }

    /**
     * @return array{country: Country, istanbul: City, cologne: City, cgn: Airport, hotel: Hotel, room: HotelRoomType}
     */
    private function catalogData(): array
    {
        $suffix = self::suffix();
        $country = (new Country())->setName('Tour External Country ' . $suffix);
        $istanbul = (new City())->setCountry($country)->setName('Istanbul')->setSlug('istanbul-' . strtolower($suffix));
        $cologne = (new City())->setCountry($country)->setName('Cologne ' . $suffix)->setSlug('cologne-' . strtolower($suffix));
        $cgn = (new Airport())->setCity($cologne)->setName('Cologne Bonn ' . $suffix)->setIataCode(self::iata());
        $hotel = (new Hotel())->setCity($istanbul)->setName('Arts Hotel Istanbul Harbiye')->setSlug('arts-hotel-tour-external-' . strtolower($suffix));
        $room = (new HotelRoomType())->setHotel($hotel)->setName('Double Room')->setCode('dbl-tour-' . strtolower($suffix));

        foreach ([$country, $istanbul, $cologne, $cgn, $hotel, $room] as $entity) {
            $this->em()->persist($entity);
        }
        $this->em()->flush();

        return compact('country', 'istanbul', 'cologne', 'cgn', 'hotel', 'room');
    }

    private function ownPackage(Airport $origin, City $destination): TourPackage
    {
        $package = (new TourPackage())
            ->setName('Our Istanbul 5 Nights')
            ->setSlug('our-istanbul-external-' . strtolower(self::suffix()))
            ->setOriginAirport($origin)
            ->setDestinationCity($destination)
            ->setDepartureDate(new \DateTimeImmutable('2026-09-10'))
            ->setReturnDate(new \DateTimeImmutable('2026-09-15'))
            ->setNights(5)
            ->setDays(6)
            ->setAdults(2)
            ->setChildren(0)
            ->setInfants(0)
            ->setPricingMode(TourPricingMode::TOTAL_PARTY)
            ->setCurrency('EUR')
            ->setTotalPrice('1490.00')
            ->setPriority(100)
            ->setActive(true)
            ->setFeatured(true)
            ->setPublicVisible(true);

        $this->em()->persist($package);
        $this->em()->flush();

        return $package;
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function resolver(): TourOfferResolver
    {
        return new TourOfferResolver(
            self::getContainer()->get(TourPackageRepository::class),
            self::getContainer()->get(ExternalTourOfferRepository::class),
        );
    }

    private static function suffix(): string
    {
        return strtolower(bin2hex(random_bytes(4)));
    }

    private static function iata(): string
    {
        return chr(random_int(65, 90)) . chr(random_int(65, 90)) . chr(random_int(65, 90));
    }
}
