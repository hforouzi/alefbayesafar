<?php

namespace App\Modules\SearchSource\Provider;

use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;

final readonly class FirecrawlProvider implements TravelDataProviderInterface
{
    public function __construct(private FirecrawlClient $client)
    {
    }

    public function getCode(): string
    {
        return 'firecrawl';
    }

    public function getLabel(): string
    {
        return 'Firecrawl';
    }

    public function supports(SearchSource $source, string $capability): bool
    {
        return $source->getProvider() === $this->getCode()
            && $source->getProviderType() === SearchSourceProviderType::FIRECRAWL
            && $source->supports($capability);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $payload = []): array
    {
        return $this->client->request($method, $path, $payload);
    }
}
