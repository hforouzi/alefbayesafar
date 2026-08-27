<?php

namespace App\Tests\Modules\SearchSource;

use App\Modules\SearchSource\Provider\FirecrawlClient;
use App\Modules\SearchSource\Provider\ProviderConfigurationException;
use App\Modules\SearchSource\Provider\ProviderRequestException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class FirecrawlClientTest extends TestCase
{
    public function testSuccessfulJsonResponseIsReturned(): void
    {
        $client = new FirecrawlClient(
            new MockHttpClient(new MockResponse('{"success":true,"data":{"ok":1}}', ['http_code' => 200])),
            'test-key',
            'https://firecrawl.test',
        );

        self::assertSame(['success' => true, 'data' => ['ok' => 1]], $client->request('GET', '/v1/test'));
    }

    public function testMissingApiKeyFailsAsConfigurationError(): void
    {
        $client = new FirecrawlClient(new MockHttpClient(), '', 'https://firecrawl.test');

        $this->expectException(ProviderConfigurationException::class);
        $client->request('GET', '/v1/test');
    }

    /**
     * @dataProvider failingStatusProvider
     */
    public function testHttpFailuresAreMapped(int $statusCode, string $expectedType): void
    {
        $client = new FirecrawlClient(
            new MockHttpClient(new MockResponse('{"error":"failed"}', ['http_code' => $statusCode])),
            'test-key',
            'https://firecrawl.test',
        );

        try {
            $client->request('GET', '/v1/test');
            self::fail('Expected provider request exception.');
        } catch (ProviderRequestException $exception) {
            self::assertSame($expectedType, $exception->getType());
            self::assertSame($statusCode, $exception->getCode());
            self::assertStringContainsString('Firecrawl HTTP ' . $statusCode, $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{0: int, 1: string}>
     */
    public static function failingStatusProvider(): iterable
    {
        yield 'authentication' => [401, ProviderRequestException::TYPE_AUTHENTICATION];
        yield 'forbidden' => [403, ProviderRequestException::TYPE_AUTHENTICATION];
        yield 'rate limit' => [429, ProviderRequestException::TYPE_RATE_LIMIT];
        yield 'server' => [500, ProviderRequestException::TYPE_SERVER];
        yield 'request' => [400, ProviderRequestException::TYPE_REQUEST];
    }

    public function testMalformedJsonFailsClearly(): void
    {
        $client = new FirecrawlClient(
            new MockHttpClient(new MockResponse('not json', ['http_code' => 200])),
            'test-key',
            'https://firecrawl.test',
        );

        try {
            $client->request('GET', '/v1/test');
            self::fail('Expected malformed response exception.');
        } catch (ProviderRequestException $exception) {
            self::assertSame(ProviderRequestException::TYPE_MALFORMED_RESPONSE, $exception->getType());
        }
    }

    public function testTransportFailureIsReportedAsFailure(): void
    {
        $client = new FirecrawlClient(
            new MockHttpClient(static fn (): MockResponse => throw new TransportException('network down')),
            'test-key',
            'https://firecrawl.test',
        );

        try {
            $client->request('GET', '/v1/test');
            self::fail('Expected transport exception.');
        } catch (ProviderRequestException $exception) {
            self::assertSame(ProviderRequestException::TYPE_TRANSPORT, $exception->getType());
        }
    }
}
