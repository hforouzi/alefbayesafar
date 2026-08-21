<?php

namespace App\Modules\Hotel\Provider;

use App\Modules\Hotel\ValueObject\HotelCandidate;
use App\Modules\Hotel\ValueObject\HotelSearchRequest;
use App\Modules\Hotel\ValueObject\HotelSearchResult;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Provider\FirecrawlProvider;
use App\Modules\SearchSource\Provider\ProviderConfigurationException;
use App\Modules\SearchSource\Provider\ProviderRequestException;

final readonly class FirecrawlHotelSearchProvider implements HotelSearchProviderInterface
{
    public function __construct(private FirecrawlProvider $firecrawlProvider)
    {
    }

    public function supports(SearchSource $source): bool
    {
        return $this->firecrawlProvider->supports($source, SearchSource::CAPABILITY_HOTEL);
    }

    public function search(SearchSource $source, HotelSearchRequest $request): HotelSearchResult
    {
        $payload = [
            'query' => $this->query($source, $request),
            'limit' => max(1, min(10, $request->limit)),
            'includeDomains' => [$source->getDomain()],
            'scrapeOptions' => [
                'formats' => ['markdown', 'links'],
            ],
        ];

        try {
            $response = $this->firecrawlProvider->request('POST', '/v2/search', $payload);
        } catch (ProviderConfigurationException|ProviderRequestException $exception) {
            return HotelSearchResult::failure($source, [$exception->getMessage()], [
                'provider' => $source->getProvider(),
                'exception' => $exception::class,
            ]);
        }

        $metadata = [
            'provider' => $source->getProvider(),
            'topLevelKeys' => array_keys($response),
        ];

        if (($response['success'] ?? true) === false) {
            return HotelSearchResult::failure($source, [$this->apiError($response)], $metadata);
        }

        $items = $this->items($response);
        $candidates = [];
        $discarded = [];
        foreach ($items as $index => $item) {
            $candidate = $this->candidateFromItem($source, $request, $item);
            if ($candidate instanceof HotelCandidate) {
                $candidates[] = $candidate;
                continue;
            }

            $discarded[] = sprintf('Result %d could not be mapped because it did not contain a usable title or name.', $index + 1);
        }

        $metadata['rawCount'] = \count($items);
        $metadata['count'] = \count($candidates);
        $metadata['discarded'] = $discarded;

        if ($items !== [] && $candidates === []) {
            return HotelSearchResult::failure($source, ['Firecrawl returned search results, but none could be mapped to hotel candidates.'], $metadata);
        }

        return HotelSearchResult::success($source, $candidates, $metadata);
    }

    private function query(SearchSource $source, HotelSearchRequest $request): string
    {
        $parts = [$request->query, 'hotel', $request->city->getName()];
        if ($request->district !== null) {
            $parts[] = $request->district->getName();
        }
        $parts[] = $request->city->getCountry()?->getName();
        $parts[] = 'site:' . $source->getDomain();

        return implode(' ', array_values(array_filter($parts, static fn (?string $part): bool => $part !== null && trim($part) !== '')));
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<int, array<string, mixed>>
     */
    private function items(array $response): array
    {
        $data = $response['data'] ?? null;
        if (\is_array($data)) {
            foreach (['web', 'results', 'searchResults'] as $key) {
                if (isset($data[$key]) && \is_array($data[$key])) {
                    return $this->listItems($data[$key]);
                }
            }

            if (array_is_list($data)) {
                return $this->listItems($data);
            }
        }

        foreach (['results', 'web'] as $key) {
            if (isset($response[$key]) && \is_array($response[$key])) {
                return $this->listItems($response[$key]);
            }
        }

        return [];
    }

    /**
     * @param array<int|string, mixed> $rawItems
     *
     * @return array<int, array<string, mixed>>
     */
    private function listItems(array $rawItems): array
    {
        $items = [];
        foreach ($rawItems as $item) {
            if (\is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function candidateFromItem(SearchSource $source, HotelSearchRequest $request, array $item): ?HotelCandidate
    {
        $metadata = \is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
        $url = $this->string($item['url'] ?? $item['sourceUrl'] ?? $item['link'] ?? $metadata['sourceURL'] ?? $metadata['url'] ?? null);
        $title = $this->string($item['title'] ?? $item['sourceTitle'] ?? $item['name'] ?? $metadata['title'] ?? null);
        $description = $this->string($item['description'] ?? $item['snippet'] ?? $metadata['description'] ?? $item['markdown'] ?? null);
        $name = $this->nameFromTitle($title, $request->query);

        if ($name === '') {
            return null;
        }

        return new HotelCandidate(
            sourceIdentifier: HotelCandidate::sourceIdentifier($source),
            sourceName: $source->getName(),
            providerCode: $source->getProvider(),
            externalId: $this->externalId($item, $url),
            sourceUrl: $url,
            sourceTitle: $title,
            name: $name,
            nameFa: null,
            address: $this->string($item['address'] ?? null),
            countryName: $request->city->getCountry()?->getName(),
            cityName: $request->city->getName(),
            districtName: $request->district?->getName(),
            stars: $this->stars($item),
            latitude: $this->coordinate($item['latitude'] ?? $item['lat'] ?? null, -90, 90),
            longitude: $this->coordinate($item['longitude'] ?? $item['lng'] ?? $item['lon'] ?? null, -180, 180),
            website: $url,
            phone: $this->string($item['phone'] ?? null),
            descriptionOriginal: $description,
            descriptionFa: null,
            images: $this->images($item),
            rawData: $item,
        );
    }

    private function nameFromTitle(?string $title, string $fallback): string
    {
        $name = trim((string) $title);
        $name = preg_replace('/\s+[-|].*$/u', '', $name) ?? $name;

        return trim($name !== '' ? $name : $fallback);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function externalId(array $item, ?string $url): ?string
    {
        foreach (['id', 'externalId', 'external_id'] as $key) {
            $value = $this->string($item[$key] ?? null);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function stars(array $item): ?int
    {
        $value = $item['stars'] ?? $item['starRating'] ?? null;
        if (!is_numeric($value)) {
            return null;
        }

        $stars = (int) $value;

        return $stars >= 1 && $stars <= 5 ? $stars : null;
    }

    private function coordinate(mixed $value, int $min, int $max): null|string
    {
        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;
        if ($number < $min || $number > $max) {
            return null;
        }

        return (string) $number;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<int, array{url: string, alt?: string|null}>
     */
    private function images(array $item): array
    {
        $images = $item['images'] ?? $item['image'] ?? [];
        if (\is_string($images)) {
            $images = [$images];
        }
        if (!\is_array($images)) {
            return [];
        }

        $normalized = [];
        foreach ($images as $image) {
            $url = \is_array($image) ? $this->string($image['url'] ?? null) : $this->string($image);
            if ($url !== null && str_starts_with($url, 'http')) {
                $normalized[] = ['url' => $url, 'alt' => \is_array($image) ? $this->string($image['alt'] ?? null) : null];
            }
        }

        return $normalized;
    }

    private function string(mixed $value): ?string
    {
        $value = \is_scalar($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function apiError(array $response): string
    {
        foreach (['error', 'message'] as $key) {
            $value = $this->string($response[$key] ?? null);
            if ($value !== null) {
                return 'Firecrawl API error: ' . $value;
            }
        }

        return 'Firecrawl API returned an unsuccessful response.';
    }
}
