<?php

namespace App\Modules\Hotel\Provider;

use App\Modules\Hotel\ValueObject\HotelCandidate;
use App\Modules\Hotel\ValueObject\HotelRoomTypeCandidate;
use App\Modules\Hotel\ValueObject\HotelSearchRequest;
use App\Modules\Hotel\ValueObject\HotelSearchResult;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Provider\FirecrawlProvider;
use App\Modules\SearchSource\Provider\ProviderConfigurationException;
use App\Modules\SearchSource\Provider\ProviderRequestException;

final readonly class FirecrawlHotelSearchProvider implements HotelSearchProviderInterface
{
    private const IMAGE_LIMIT = 8;

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
            nameFa: $this->string($item['nameFa'] ?? $metadata['nameFa'] ?? null),
            address: $this->string($item['address'] ?? $metadata['address'] ?? null),
            countryName: $request->city->getCountry()?->getName(),
            cityName: $request->city->getName(),
            districtName: $request->district?->getName(),
            stars: $this->stars($item),
            latitude: $this->coordinate($item['latitude'] ?? $item['lat'] ?? null, -90, 90),
            longitude: $this->coordinate($item['longitude'] ?? $item['lng'] ?? $item['lon'] ?? null, -180, 180),
            website: $url,
            phone: $this->string($item['phone'] ?? null),
            descriptionOriginal: $description,
            descriptionFa: $this->string($item['descriptionFa'] ?? $metadata['descriptionFa'] ?? null),
            images: $this->images($item),
            rawData: $this->compactRawData($item),
            roomTypes: $this->roomTypes($item),
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
            $text = implode(' ', array_filter([
                $this->string($item['title'] ?? null),
                $this->string($item['description'] ?? null),
                $this->string($item['markdown'] ?? null),
            ]));
            $value = preg_match('/\b([1-5])\s*-?\s*star\b/i', $text, $match) === 1 ? $match[1] : null;
        }
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
        $urls = [];
        $metadata = \is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
        foreach ([$item['images'] ?? null, $item['image'] ?? null, $metadata['ogImage'] ?? null, $metadata['image'] ?? null] as $images) {
            foreach ($this->imageUrlsFromValue($images) as $url) {
                $urls[$url] = $url;
            }
        }

        foreach (['markdown', 'description'] as $field) {
            $text = $this->string($item[$field] ?? null);
            if ($text !== null && preg_match_all('#https?://[^\s\]\)"\'<>]+?\.(?:jpe?g|png|webp|gif)(?:\?[^\s\]\)"\'<>]*)?#i', $text, $matches) > 0) {
                foreach ($matches[0] as $url) {
                    $urls[$url] = $url;
                }
            }
        }

        $normalized = [];
        foreach (array_slice(array_values($urls), 0, self::IMAGE_LIMIT) as $url) {
            $normalized[] = ['url' => $url, 'alt' => $this->string($item['title'] ?? null)];
        }

        return $normalized;
    }

    /**
     * @return string[]
     */
    private function imageUrlsFromValue(mixed $images): array
    {
        if (\is_string($images)) {
            $images = [$images];
        }
        if (!\is_array($images)) {
            return [];
        }

        $urls = [];
        foreach ($images as $image) {
            $url = \is_array($image) ? $this->string($image['url'] ?? $image['src'] ?? null) : $this->string($image);
            if ($url !== null && preg_match('#^https?://#i', $url) === 1) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function compactRawData(array $item): array
    {
        $amenityText = $this->amenityText($item);
        unset($item['markdown'], $item['html'], $item['content']);
        if ($amenityText !== null) {
            $item['amenityText'] = $amenityText;
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return HotelRoomTypeCandidate[]
     */
    private function roomTypes(array $item): array
    {
        $rawRooms = [];
        foreach ([
            $item['rooms'] ?? null,
            $item['roomTypes'] ?? null,
            \is_array($item['json'] ?? null) ? ($item['json']['rooms'] ?? null) : null,
            \is_array($item['extract'] ?? null) ? ($item['extract']['rooms'] ?? null) : null,
            \is_array($item['metadata'] ?? null) ? ($item['metadata']['rooms'] ?? null) : null,
        ] as $candidateRooms) {
            if (\is_array($candidateRooms)) {
                $rawRooms = $candidateRooms;
                break;
            }
        }

        $rooms = [];
        foreach ($rawRooms as $rawRoom) {
            if (!\is_array($rawRoom)) {
                continue;
            }

            $room = HotelRoomTypeCandidate::fromArray($rawRoom);
            if ($room instanceof HotelRoomTypeCandidate) {
                $rooms[] = $room;
            }
        }

        return $rooms;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function amenityText(array $item): ?string
    {
        $parts = [];
        foreach (['description', 'snippet', 'markdown'] as $field) {
            $value = $this->string($item[$field] ?? null);
            if ($value !== null) {
                $parts[] = mb_substr(preg_replace('/\s+/u', ' ', $value) ?? $value, 0, 2000);
            }
        }

        return $parts !== [] ? implode(' ', $parts) : null;
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
