<?php

namespace App\Tests\Modules\Destination;

use App\Modules\Destination\Provider\FirecrawlDestinationInsightProvider;
use App\Modules\SearchSource\Provider\FirecrawlClient;
use App\Modules\SearchSource\Provider\FirecrawlProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class FirecrawlDestinationInsightProviderTest extends TestCase
{
    public function testSearchMapsRealFirecrawlResultsToDestinationInsights(): void
    {
        $captured = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse(json_encode([
                'success' => true,
                'data' => [
                    'web' => [
                        [
                            'title' => 'Istinye Park - Tripadvisor',
                            'url' => 'https://www.tripadvisor.com/Attraction_Review-istinye-park.html',
                            'description' => 'A large shopping mall in Istanbul. Rated 4.5/5 based on 1,234 reviews.',
                        ],
                        [
                            'title' => 'Grand Bazaar - Tripadvisor',
                            'url' => 'https://www.tripadvisor.com/Attraction_Review-grand-bazaar.html',
                            'description' => 'One of the largest covered markets in the world.',
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR));
        }, 'https://firecrawl.test');

        $provider = new FirecrawlDestinationInsightProvider(new FirecrawlProvider(new FirecrawlClient($httpClient, 'key', 'https://firecrawl.test')));

        $result = $provider->search('Istanbul', 'Turkey', 'shopping', 5);

        self::assertTrue($result->success);
        self::assertSame('POST', $captured['method']);
        self::assertStringEndsWith('/v2/search', $captured['url']);
        $body = json_decode((string) ($captured['options']['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['tripadvisor.com'], $body['includeDomains']);
        self::assertStringContainsString('shopping malls', $body['query']);
        self::assertStringContainsString('Istanbul', $body['query']);

        self::assertCount(2, $result->insights);
        self::assertSame('Istinye Park', $result->insights[0]->title);
        self::assertSame('shopping', $result->insights[0]->category);
        self::assertSame('Istanbul', $result->insights[0]->city);
        self::assertSame('Tripadvisor', $result->insights[0]->source);
        self::assertSame('4.5', $result->insights[0]->rating);
        self::assertSame(1234, $result->insights[0]->reviewCount);
        self::assertSame('https://www.tripadvisor.com/Attraction_Review-istinye-park.html', $result->insights[0]->sourceUrl);

        // Second result's snippet has no rating/review text — must stay null, never guessed.
        self::assertSame('Grand Bazaar', $result->insights[1]->title);
        self::assertNull($result->insights[1]->rating);
        self::assertNull($result->insights[1]->reviewCount);
    }

    public function testProviderFailureIsReportedNotSwallowed(): void
    {
        $provider = new FirecrawlDestinationInsightProvider(new FirecrawlProvider(new FirecrawlClient(new MockHttpClient(new MockResponse('{"error":"no"}', ['http_code' => 429])), 'key', 'https://firecrawl.test')));

        $result = $provider->search('Istanbul', 'Turkey', 'shopping', 5);

        self::assertFalse($result->success);
        self::assertSame([], $result->insights);
        self::assertNotSame([], $result->errors);
    }

    public function testMissingApiKeyIsReportedAsFailureNotEmptySuccess(): void
    {
        $provider = new FirecrawlDestinationInsightProvider(new FirecrawlProvider(new FirecrawlClient(new MockHttpClient(), null, 'https://firecrawl.test')));

        $result = $provider->search('Istanbul', 'Turkey', 'shopping', 5);

        self::assertFalse($result->success);
        self::assertSame([], $result->insights);
        self::assertNotSame([], $result->errors);
    }

    public function testDuplicateTitlesAreDeduplicated(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'success' => true,
            'data' => [
                'web' => [
                    ['title' => 'Istinye Park - Tripadvisor', 'url' => 'https://www.tripadvisor.com/a', 'description' => 'Mall.'],
                    ['title' => 'Istinye Park - Tripadvisor', 'url' => 'https://www.tripadvisor.com/b', 'description' => 'Same mall, different snippet.'],
                ],
            ],
        ], JSON_THROW_ON_ERROR)), 'https://firecrawl.test');

        $provider = new FirecrawlDestinationInsightProvider(new FirecrawlProvider(new FirecrawlClient($httpClient, 'key', 'https://firecrawl.test')));

        $result = $provider->search('Istanbul', 'Turkey', 'shopping', 5);

        self::assertTrue($result->success);
        self::assertCount(1, $result->insights);
    }
}
