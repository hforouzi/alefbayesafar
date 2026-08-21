<?php

namespace App\Modules\SearchSource\Provider;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class FirecrawlClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ?string $apiKey,
        private string $baseUrl = 'https://api.firecrawl.dev',
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $payload = []): array
    {
        $apiKey = trim((string) $this->apiKey);
        if ($apiKey === '') {
            throw new ProviderConfigurationException('Firecrawl API key is not configured.');
        }

        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
            ],
        ];

        if ($payload !== []) {
            $options['json'] = $payload;
        }

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            throw new ProviderRequestException(
                ProviderRequestException::TYPE_TRANSPORT,
                'Firecrawl transport failure: ' . $exception->getMessage(),
                0,
                $exception,
            );
        }

        if ($statusCode === 401 || $statusCode === 403) {
            throw new ProviderRequestException(ProviderRequestException::TYPE_AUTHENTICATION, 'Firecrawl authentication failed.', $statusCode);
        }

        if ($statusCode === 429) {
            throw new ProviderRequestException(ProviderRequestException::TYPE_RATE_LIMIT, 'Firecrawl rate limit reached.', $statusCode);
        }

        if ($statusCode >= 500) {
            throw new ProviderRequestException(ProviderRequestException::TYPE_SERVER, 'Firecrawl server error.', $statusCode);
        }

        if ($statusCode >= 400) {
            throw new ProviderRequestException(ProviderRequestException::TYPE_REQUEST, 'Firecrawl request failed.', $statusCode);
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ProviderRequestException(
                ProviderRequestException::TYPE_MALFORMED_RESPONSE,
                'Firecrawl returned malformed JSON.',
                $statusCode,
                $exception,
            );
        }

        if (!\is_array($decoded)) {
            throw new ProviderRequestException(ProviderRequestException::TYPE_MALFORMED_RESPONSE, 'Firecrawl response was not a JSON object.', $statusCode);
        }

        return $decoded;
    }
}
