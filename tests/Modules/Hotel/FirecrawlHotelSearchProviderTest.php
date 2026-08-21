<?php

namespace App\Tests\Modules\Hotel;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Hotel\Provider\FirecrawlHotelSearchProvider;
use App\Modules\Hotel\ValueObject\HotelSearchRequest;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\SearchSource\Provider\FirecrawlClient;
use App\Modules\SearchSource\Provider\FirecrawlProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class FirecrawlHotelSearchProviderTest extends KernelTestCase
{
    public function testFirecrawlSearchMapsResultsToHotelCandidates(): void
    {
        $captured = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse(json_encode([
                'success' => true,
                'data' => [
                    'web' => [[
                        'id' => 'hotel-123',
                        'title' => 'Arts Hotel Istanbul - Booking',
                        'url' => 'https://booking.com/hotel/tr/arts.html',
                        'description' => 'Central Istanbul hotel.',
                        'stars' => 5,
                        'latitude' => 41.1,
                        'longitude' => 29.1,
                        'metadata' => [
                            'title' => 'Fallback title',
                        ],
                    ]],
                ],
                'creditsUsed' => 1,
                'id' => 'search-123',
            ], JSON_THROW_ON_ERROR));
        }, 'https://firecrawl.test');

        $provider = new FirecrawlHotelSearchProvider(new FirecrawlProvider(new FirecrawlClient($httpClient, 'key', 'https://firecrawl.test')));
        $source = $this->source();
        $city = (new City())->setCountry((new Country())->setName('Turkey'))->setName('Istanbul')->setSlug('istanbul');

        $result = $provider->search($source, new HotelSearchRequest('Arts Hotel', $city));

        self::assertTrue($result->success);
        self::assertSame('POST', $captured['method']);
        self::assertStringEndsWith('/v2/search', $captured['url']);
        $body = json_decode((string) ($captured['options']['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['booking.com'], $body['includeDomains']);
        self::assertCount(1, $result->candidates);
        self::assertSame('booking', $result->candidates[0]->sourceIdentifier);
        self::assertSame('firecrawl', $result->candidates[0]->providerCode);
        self::assertSame('hotel-123', $result->candidates[0]->externalId);
        self::assertSame('Arts Hotel Istanbul', $result->candidates[0]->name);
        self::assertSame(5, $result->candidates[0]->stars);
        self::assertSame(1, $result->metadata['rawCount']);
        self::assertSame(1, $result->metadata['count']);
    }

    public function testFirecrawlSearchMapsTopLevelResultsForLegacyResponses(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'success' => true,
            'results' => [[
                'title' => 'Legacy Arts Hotel - Booking',
                'url' => 'https://booking.com/hotel/tr/legacy-arts.html',
                'description' => 'Legacy response.',
            ]],
        ], JSON_THROW_ON_ERROR)), 'https://firecrawl.test');

        $provider = new FirecrawlHotelSearchProvider(new FirecrawlProvider(new FirecrawlClient($httpClient, 'key', 'https://firecrawl.test')));
        $city = (new City())->setCountry((new Country())->setName('Turkey'))->setName('Istanbul')->setSlug('istanbul');

        $result = $provider->search($this->source(), new HotelSearchRequest('Arts Hotel', $city));

        self::assertTrue($result->success);
        self::assertCount(1, $result->candidates);
        self::assertNull($result->candidates[0]->externalId);
        self::assertSame('https://booking.com/hotel/tr/legacy-arts.html', $result->candidates[0]->sourceUrl);
    }

    public function testFirecrawlFailureIsReportedAsFailureResult(): void
    {
        $provider = new FirecrawlHotelSearchProvider(new FirecrawlProvider(new FirecrawlClient(new MockHttpClient(new MockResponse('{"error":"no"}', ['http_code' => 429])), 'key', 'https://firecrawl.test')));
        $city = (new City())->setCountry((new Country())->setName('Turkey'))->setName('Istanbul')->setSlug('istanbul');

        $result = $provider->search($this->source(), new HotelSearchRequest('Arts Hotel', $city));

        self::assertFalse($result->success);
        self::assertSame([], $result->candidates);
        self::assertNotSame([], $result->errors);
    }

    public function testFirecrawlHttp200ApiErrorIsReportedAsFailureResult(): void
    {
        $provider = new FirecrawlHotelSearchProvider(new FirecrawlProvider(new FirecrawlClient(new MockHttpClient(new MockResponse(json_encode([
            'success' => false,
            'error' => 'Invalid search request.',
        ], JSON_THROW_ON_ERROR), ['http_code' => 200])), 'key', 'https://firecrawl.test')));
        $city = (new City())->setCountry((new Country())->setName('Turkey'))->setName('Istanbul')->setSlug('istanbul');

        $result = $provider->search($this->source(), new HotelSearchRequest('Arts Hotel', $city));

        self::assertFalse($result->success);
        self::assertSame([], $result->candidates);
        self::assertSame(['Firecrawl API error: Invalid search request.'], $result->errors);
        self::assertSame(['success', 'error'], $result->metadata['topLevelKeys']);
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
}
