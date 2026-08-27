<?php

namespace App\Tests\Modules\Hotel;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelSourceReference;
use App\Modules\Hotel\Enum\HotelOfferSearchStatus;
use App\Modules\Hotel\Provider\FirecrawlHotelOfferProvider;
use App\Modules\Hotel\ValueObject\HotelOfferCandidate;
use App\Modules\Hotel\ValueObject\HotelOfferSearchRequest;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\SearchSource\Provider\FirecrawlClient;
use App\Modules\SearchSource\Provider\FirecrawlProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class FirecrawlHotelOfferProviderTest extends TestCase
{
    public function testSupportsValidFirecrawlHotelSearchSource(): void
    {
        $provider = $this->provider([['success' => true, 'data' => ['json' => ['offers' => []]]]]);

        self::assertTrue($provider->supports($this->source()));
    }

    public function testRejectsUnsupportedProviderAndSourceWithoutHotelCapability(): void
    {
        $provider = $this->provider([['success' => true, 'data' => ['json' => ['offers' => []]]]]);

        self::assertFalse($provider->supports($this->source()->setProvider('manual')));
        self::assertFalse($provider->supports($this->source()->setCapabilities([SearchSource::CAPABILITY_REVIEW])));
    }

    public function testValidOfferMapsToCandidateWithCommercialAndTechnicalAttribution(): void
    {
        $captured = [];
        $provider = $this->provider([$this->scrapeResponse([
            [
                'externalOfferId' => 'rate-123',
                'roomName' => 'Deluxe Double Room',
                'boardType' => 'Breakfast included',
                'currency' => 'eur',
                'totalPrice' => '620.00',
                'bookingUrl' => 'https://booking.com/reserve/offer-123',
                'availabilityStatus' => 'available',
            ],
        ])], $captured);

        $hotel = $this->hotelWithReference('https://booking.com/hotel/tr/arts.html');
        $result = $provider->search($this->source(), $hotel, $this->request());

        self::assertTrue($result->success);
        self::assertSame(HotelOfferSearchStatus::OFFERS_FOUND, $result->status);
        self::assertCount(1, $result->candidates);
        self::assertSame('POST', $captured[0]['method']);
        self::assertStringEndsWith('/v2/scrape', $captured[0]['url']);
        $body = $this->body($captured[0]);
        self::assertStringStartsWith('https://booking.com/hotel/tr/arts.html?', $body['url']);
        self::assertStringContainsString('checkin=2026-09-10', $body['url']);
        self::assertStringContainsString('checkout=2026-09-15', $body['url']);
        self::assertStringContainsString('group_adults=2', $body['url']);
        self::assertStringContainsString('group_children=1', $body['url']);
        self::assertStringContainsString('no_rooms=1', $body['url']);
        self::assertStringContainsString('age=4', $body['url']);
        self::assertArrayNotHasKey('jsonOptions', $body);
        self::assertSame('json', $body['formats'][0]['type']);
        self::assertSame('object', $body['formats'][0]['schema']['type']);
        self::assertArrayHasKey('offers', $body['formats'][0]['schema']['properties']);
        self::assertStringContainsString('check-in 2026-09-10', $body['formats'][0]['prompt']);
        self::assertStringContainsString('2 adult(s)', $body['formats'][0]['prompt']);
        self::assertStringContainsString('1 child(ren)', $body['formats'][0]['prompt']);
        self::assertStringContainsString('child ages [4]', $body['formats'][0]['prompt']);
        self::assertArrayHasKey('childrenAges', $body['formats'][0]['schema']['properties']['offers']['items']['properties']);
        self::assertSame('Booking', $result->candidates[0]->sourceName);
        self::assertSame('booking', $result->candidates[0]->sourceIdentifier);
        self::assertSame('firecrawl', $result->candidates[0]->providerCode);
        self::assertSame('rate-123', $result->candidates[0]->externalOfferId);
        self::assertSame('Deluxe Double Room', $result->candidates[0]->roomName);
        self::assertSame('Breakfast included', $result->candidates[0]->boardType);
        self::assertSame('EUR', $result->candidates[0]->currency);
        self::assertSame('620.00', $result->candidates[0]->totalPrice);
        self::assertIsString($result->candidates[0]->totalPrice);
        self::assertSame('available', $result->candidates[0]->availabilityStatus);
        self::assertSame('source_reference_booking_context', $result->metadata['urlSelection']);
        self::assertTrue($result->metadata['contextUrlUsed']);
        self::assertSame('https://booking.com/hotel/tr/arts.html', $result->metadata['originalSourceUrl']);
        self::assertTrue($result->metadata['sourceReferenceDiagnostics'][0]['sourceReferenceIdentityMatch']);
    }

    public function testBookingContextUrlPreservesExistingQueryParamsAndMultipleChildAges(): void
    {
        $captured = [];
        $provider = $this->provider([$this->scrapeResponse([[
            'currency' => 'EUR',
            'totalPrice' => '620.00',
        ]])], $captured);

        $request = new HotelOfferSearchRequest(
            new \DateTimeImmutable('2026-09-10'),
            new \DateTimeImmutable('2026-09-15'),
            2,
            2,
            [8, 4],
        );

        $result = $provider->search($this->source(), $this->hotelWithReference('https://www.booking.com/hotel/tr/arts.html?lang=en-us&checkin=2025-01-01&age=15'), $request);

        self::assertTrue($result->success);
        self::assertCount(1, $result->candidates);
        $body = $this->body($captured[0]);
        self::assertStringContainsString('lang=en-us', $body['url']);
        self::assertStringContainsString('checkin=2026-09-10', $body['url']);
        self::assertStringContainsString('checkout=2026-09-15', $body['url']);
        self::assertStringContainsString('group_children=2', $body['url']);
        self::assertStringContainsString('age=4', $body['url']);
        self::assertStringContainsString('age=8', $body['url']);
        self::assertStringNotContainsString('age=15', $body['url']);
    }

    public function testMultipleOffersRemainSeparate(): void
    {
        $provider = $this->provider([$this->scrapeResponse([
            $this->offer(['externalOfferId' => 'room-only', 'roomName' => 'Standard Room', 'boardType' => 'Room only', 'totalPrice' => '600']),
            $this->offer(['externalOfferId' => 'breakfast', 'roomName' => 'Standard Room', 'boardType' => 'Breakfast', 'totalPrice' => '660.00']),
        ])]);

        $result = $provider->search($this->genericSource(), $this->hotelWithReference('https://offers.example.test/hotel/tr/arts.html', $this->genericSource()), $this->request());

        self::assertTrue($result->success);
        self::assertCount(2, $result->candidates);
        self::assertSame('600.00', $result->candidates[0]->totalPrice);
        self::assertSame('660.00', $result->candidates[1]->totalPrice);
    }

    public function testMissingPriceCurrencyAmbiguousPriceWrongDatesAndWrongTravelersAreRejected(): void
    {
        $provider = $this->provider([$this->scrapeResponse([
            $this->offer(['totalPrice' => null]),
            $this->offer(['currency' => null]),
            $this->offer(['totalPrice' => '1,234.56']),
            $this->offer(['totalPrice' => '1.234,56']),
            $this->offer(['checkIn' => '2026-09-11']),
            $this->offer(['adults' => 1]),
            $this->offer(['children' => 0]),
            $this->offer(['childrenAges' => [15]]),
        ])]);

        $result = $provider->search($this->genericSource(), $this->hotelWithReference('https://offers.example.test/hotel/tr/arts.html', $this->genericSource()), $this->request());

        self::assertTrue($result->success);
        self::assertSame(HotelOfferSearchStatus::NO_DATA, $result->status);
        self::assertSame([], $result->candidates);
        self::assertSame(8, $result->metadata['rejectedCandidateCount']);
    }

    public function testPartialExtractionReturnsValidCandidatesAndIgnoresMalformedOffers(): void
    {
        $provider = $this->provider([$this->scrapeResponse([
            $this->offer(['externalOfferId' => null]),
            $this->offer(['totalPrice' => 'around 620']),
            $this->offer(['currency' => '']),
        ])]);

        $result = $provider->search($this->genericSource(), $this->hotelWithReference('https://offers.example.test/hotel/tr/arts.html', $this->genericSource()), $this->request());

        self::assertTrue($result->success);
        self::assertSame(HotelOfferSearchStatus::OFFERS_FOUND, $result->status);
        self::assertCount(1, $result->candidates);
        self::assertNull($result->candidates[0]->externalOfferId);
        self::assertSame(1, $result->metadata['acceptedCandidateCount']);
        self::assertSame(2, $result->metadata['rejectedCandidateCount']);
    }

    public function testProviderFailureIsFailureNotEmptySuccess(): void
    {
        $provider = $this->provider([new MockResponse('{"error":"too many"}', ['http_code' => 429])]);

        $result = $provider->search($this->genericSource(), $this->hotelWithReference('https://offers.example.test/hotel/tr/arts.html', $this->genericSource()), $this->request());

        self::assertFalse($result->success);
        self::assertSame(HotelOfferSearchStatus::PROVIDER_ERROR, $result->status);
        self::assertSame([], $result->candidates);
        self::assertNotSame([], $result->errors);
    }

    public function testLoadedPageWithoutReliableOffersIsNoData(): void
    {
        $provider = $this->provider([$this->scrapeResponse([])]);

        $result = $provider->search($this->genericSource(), $this->hotelWithReference('https://offers.example.test/hotel/tr/arts.html', $this->genericSource()), $this->request());

        self::assertTrue($result->success);
        self::assertSame(HotelOfferSearchStatus::NO_DATA, $result->status);
        self::assertSame([], $result->candidates);
        self::assertSame(0, $result->metadata['rawResultCount']);
    }

    public function testExplicitSoldOutSignalIsDistinctFromNoData(): void
    {
        $provider = $this->provider([[
            'success' => true,
            'data' => [
                'json' => [
                    'offers' => [],
                    'soldOut' => true,
                ],
            ],
        ]]);

        $result = $provider->search($this->genericSource(), $this->hotelWithReference('https://offers.example.test/hotel/tr/arts.html', $this->genericSource()), $this->request());

        self::assertTrue($result->success);
        self::assertSame(HotelOfferSearchStatus::SOLD_OUT, $result->status);
        self::assertSame([], $result->candidates);
    }

    public function testNoSourceReferenceReturnsClearEmptyResultWithoutFallbackSearch(): void
    {
        $captured = [];
        $provider = $this->provider([], $captured);

        $result = $provider->search($this->source(), $this->hotel(), $this->request());

        self::assertTrue($result->success);
        self::assertSame(HotelOfferSearchStatus::NO_DATA, $result->status);
        self::assertSame([], $result->candidates);
        self::assertSame([], $captured);
        self::assertSame('none', $result->metadata['urlSelection']);
        self::assertSame('No source reference available for this hotel.', $result->metadata['sourceReferenceWarning']);
        self::assertFalse($result->metadata['fallbackSearchUsed']);
    }

    public function testOfferSearchUsesOnlyTheCurrentHotelsStoredSourceReference(): void
    {
        $captured = [];
        $provider = $this->provider([$this->scrapeResponse([[
            'currency' => 'USD',
            'totalPrice' => '716.00',
            'roomName' => 'Standard Room',
        ]])], $captured);

        $hotel = $this->hotelWithReference(
            'https://booking.com/hotel/tr/hotel-a.html',
            null,
            'Hotel A',
            'Hotel A',
        );
        $result = $provider->search($this->source(), $hotel, $this->request());

        self::assertTrue($result->success);
        self::assertCount(1, $result->candidates);
        self::assertCount(1, $captured);
        self::assertStringEndsWith('/v2/scrape', $captured[0]['url']);
        self::assertStringStartsWith('https://booking.com/hotel/tr/hotel-a.html?', $this->body($captured[0])['url']);
        self::assertArrayNotHasKey('firecrawlSearch', $result->metadata);
        self::assertFalse($result->metadata['fallbackSearchUsed']);
    }

    public function testDifferentHotelsScrapeOnlyTheirOwnStoredSourceReferenceUrls(): void
    {
        $captured = [];
        $provider = $this->provider([
            $this->scrapeResponse([['currency' => 'USD', 'totalPrice' => '716.00']]),
            $this->scrapeResponse([['currency' => 'USD', 'totalPrice' => '820.00']]),
        ], $captured);

        $hotelA = $this->hotelWithReference('https://booking.com/hotel/tr/hotel-a.html', null, 'Hotel A', 'Hotel A');
        $hotelB = $this->hotelWithReference('https://booking.com/hotel/tr/hotel-b.html', null, 'Hotel B', 'Hotel B');

        $resultA = $provider->search($this->source(), $hotelA, $this->request());
        $resultB = $provider->search($this->source(), $hotelB, $this->request());

        self::assertTrue($resultA->success);
        self::assertTrue($resultB->success);
        self::assertStringStartsWith('https://booking.com/hotel/tr/hotel-a.html?', $this->body($captured[0])['url']);
        self::assertStringStartsWith('https://booking.com/hotel/tr/hotel-b.html?', $this->body($captured[1])['url']);
        self::assertSame('716.00', $resultA->candidates[0]->totalPrice);
        self::assertSame('820.00', $resultB->candidates[0]->totalPrice);
    }

    public function testMatchingBookingReferenceIsAcceptedWithoutFallbackSearch(): void
    {
        $captured = [];
        $provider = $this->provider([$this->scrapeResponse([['currency' => 'EUR', 'totalPrice' => '620.00']])], $captured);

        $hotel = $this->hotelWithReference(
            'https://booking.com/hotel/tr/arts-hotel-taksim.html',
            null,
            'Arts Hotel Taksim, Istanbul',
            'Arts Hotel Taksim, Istanbul (updated prices 2026)',
        );
        $result = $provider->search($this->source(), $hotel, $this->request());

        self::assertTrue($result->success);
        self::assertCount(1, $captured);
        self::assertTrue($result->metadata['sourceReferenceDiagnostics'][0]['sourceReferenceIdentityMatch']);
        self::assertSame('source_reference_booking_context', $result->metadata['urlSelection']);
    }

    /**
     * @param array<int, array<string, mixed>|MockResponse> $responses
     * @param array<int, array<string, mixed>> $captured
     */
    private function provider(array $responses, array &$captured = []): FirecrawlHotelOfferProvider
    {
        $queue = $responses;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$queue, &$captured): MockResponse {
            $captured[] = ['method' => $method, 'url' => $url, 'options' => $options];
            $response = array_shift($queue);

            if ($response instanceof MockResponse) {
                return $response;
            }

            return new MockResponse(json_encode($response, JSON_THROW_ON_ERROR));
        }, 'https://firecrawl.test');

        return new FirecrawlHotelOfferProvider(new FirecrawlProvider(new FirecrawlClient($httpClient, 'key', 'https://firecrawl.test')));
    }

    /**
     * @param array<int, array<string, mixed>> $offers
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private function scrapeResponse(array $offers, array $metadata = []): array
    {
        return [
            'success' => true,
            'data' => [
                'json' => [
                    'offers' => $offers,
                ],
                'metadata' => $metadata,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function offer(array $override = []): array
    {
        return array_replace([
            'externalOfferId' => 'offer-123',
            'roomName' => 'Deluxe Double Room',
            'boardType' => 'Breakfast included',
            'currency' => 'eur',
            'totalPrice' => '620.00',
            'bookingUrl' => 'https://booking.com/reserve/offer-123',
            'availabilityStatus' => 'available',
            'checkIn' => '2026-09-10',
            'checkOut' => '2026-09-15',
            'adults' => 2,
            'children' => 1,
            'childrenAges' => [4],
        ], $override);
    }

    private function source(): SearchSource
    {
        return (new SearchSource())
            ->setName('Booking')
            ->setDomain('booking.com')
            ->setProvider('firecrawl')
            ->setProviderType(SearchSourceProviderType::FIRECRAWL)
            ->setCapabilities([SearchSource::CAPABILITY_HOTEL]);
    }

    private function genericSource(): SearchSource
    {
        return (new SearchSource())
            ->setName('Generic Offers')
            ->setDomain('offers.example.test')
            ->setProvider('firecrawl')
            ->setProviderType(SearchSourceProviderType::FIRECRAWL)
            ->setCapabilities([SearchSource::CAPABILITY_HOTEL]);
    }

    private function hotelWithReference(string $url, ?SearchSource $source = null, ?string $sourceTitle = null, ?string $hotelName = null): Hotel
    {
        $source ??= $this->source();
        $hotel = $this->hotel($hotelName);
        $hotel->addSourceReference((new HotelSourceReference())
            ->setSource(HotelOfferCandidate::sourceIdentifier($source))
            ->setSourceUrl($url)
            ->setSourceTitle($sourceTitle));

        return $hotel;
    }

    private function hotel(?string $name = null): Hotel
    {
        return (new Hotel())
            ->setName($name ?? 'Arts Hotel Istanbul')
            ->setSlug('arts-hotel-istanbul')
            ->setCity((new City())->setCountry((new Country())->setName('Turkey'))->setName('Istanbul')->setSlug('istanbul'));
    }

    private function request(): HotelOfferSearchRequest
    {
        return new HotelOfferSearchRequest(
            new \DateTimeImmutable('2026-09-10'),
            new \DateTimeImmutable('2026-09-15'),
            2,
            1,
            [4],
        );
    }

    /**
     * @param array<string, mixed> $captured
     *
     * @return array<string, mixed>
     */
    private function body(array $captured): array
    {
        $body = json_decode((string) ($captured['options']['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($body);

        return $body;
    }
}
