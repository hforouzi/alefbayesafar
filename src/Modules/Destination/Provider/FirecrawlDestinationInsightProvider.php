<?php

namespace App\Modules\Destination\Provider;

use App\Modules\Destination\ValueObject\DestinationInsight;
use App\Modules\Destination\ValueObject\DestinationInsightResult;
use App\Modules\SearchSource\Provider\FirecrawlProvider;
use App\Modules\SearchSource\Provider\ProviderConfigurationException;
use App\Modules\SearchSource\Provider\ProviderRequestException;

/**
 * Real destination-advice results sourced through Firecrawl's web search,
 * scoped to Tripadvisor. Same request pattern as FirecrawlHotelSearchProvider
 * (Firecrawl /v2/search), so it inherits the same "provider is a
 * replaceable layer" boundary rather than talking to Tripadvisor directly.
 *
 * Only maps facts the search response actually contains. Rating/review
 * count are extracted from the returned snippet text when present and
 * left null otherwise — never guessed or averaged across results.
 */
final readonly class FirecrawlDestinationInsightProvider implements DestinationInsightProviderInterface
{
    private const CATEGORY_QUERIES = [
        'shopping' => 'best shopping malls and markets',
        'food' => 'best restaurants and food markets',
        'history' => 'best historical attractions and museums',
        'nightlife' => 'best nightlife and entertainment spots',
        'family' => 'best family activities and attractions',
    ];

    public function __construct(private FirecrawlProvider $firecrawlProvider)
    {
    }

    public function getCode(): string
    {
        return 'firecrawl_tripadvisor';
    }

    public function search(string $cityName, ?string $countryName, string $category, int $limit): DestinationInsightResult
    {
        $payload = [
            'query' => $this->query($cityName, $countryName, $category),
            'limit' => max(1, min(10, $limit)),
            'includeDomains' => ['tripadvisor.com'],
            'scrapeOptions' => [
                'formats' => ['markdown'],
            ],
        ];

        try {
            $response = $this->firecrawlProvider->request('POST', '/v2/search', $payload);
        } catch (ProviderConfigurationException|ProviderRequestException $exception) {
            return DestinationInsightResult::failure([$exception->getMessage()]);
        }

        if (($response['success'] ?? true) === false) {
            return DestinationInsightResult::failure([$this->apiError($response)]);
        }

        $insights = [];
        $seen = [];
        foreach ($this->items($response) as $item) {
            $insight = $this->insightFromItem($item, $cityName, $category);
            if ($insight === null) {
                continue;
            }

            $key = mb_strtolower($insight->title);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $insights[] = $insight;

            if (\count($insights) >= $limit) {
                break;
            }
        }

        return DestinationInsightResult::success($insights);
    }

    private function query(string $cityName, ?string $countryName, string $category): string
    {
        $topic = self::CATEGORY_QUERIES[$category] ?? sprintf('best %s places', $category);
        $parts = [$topic, 'in', $cityName];
        if ($countryName !== null) {
            $parts[] = $countryName;
        }

        return implode(' ', $parts);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function insightFromItem(array $item, string $cityName, string $category): ?DestinationInsight
    {
        $metadata = \is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
        $title = $this->string($item['title'] ?? $metadata['title'] ?? null);
        if ($title === null) {
            return null;
        }
        $title = $this->cleanTitle($title);
        if ($title === '') {
            return null;
        }

        $url = $this->string($item['url'] ?? $metadata['sourceURL'] ?? null);
        $description = $this->string($item['description'] ?? $item['markdown'] ?? $metadata['description'] ?? null);

        return new DestinationInsight(
            title: $title,
            category: $category,
            city: $cityName,
            source: 'Tripadvisor',
            rating: $description !== null ? $this->extractRating($description) : null,
            reviewCount: $description !== null ? $this->extractReviewCount($description) : null,
            sourceUrl: $url,
            factualSummaryFields: $description !== null ? ['summary' => mb_substr(preg_replace('/\s+/u', ' ', $description) ?? $description, 0, 220)] : [],
        );
    }

    private function cleanTitle(string $title): string
    {
        $title = preg_replace('/\s*[-|]\s*Tripadvisor.*$/iu', '', $title) ?? $title;

        return trim($title);
    }

    private function extractRating(string $text): ?string
    {
        if (preg_match('/(\d(?:\.\d)?)\s*(?:\/\s*5|out of 5|stars?)/iu', $text, $match) === 1) {
            $value = (float) $match[1];

            return $value >= 0 && $value <= 5 ? $match[1] : null;
        }

        return null;
    }

    private function extractReviewCount(string $text): ?int
    {
        if (preg_match('/([\d,]+)\s*review/iu', $text, $match) === 1) {
            return (int) str_replace(',', '', $match[1]);
        }

        return null;
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

    private function string(mixed $value): ?string
    {
        $value = \is_scalar($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
    }
}
