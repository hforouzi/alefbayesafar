<?php

namespace App\Modules\Destination\Provider;

use App\Modules\Destination\ValueObject\DestinationCandidate;
use App\Modules\Destination\ValueObject\DestinationEntityType;
use App\Modules\Destination\ValueObject\DestinationImportRequest;
use App\Modules\Destination\ValueObject\DestinationProviderResult;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WikivoyageDestinationProvider implements DestinationProviderInterface
{
    private const API_URL = 'https://en.wikivoyage.org/w/api.php';
    private const PAGE_BASE_URL = 'https://en.wikivoyage.org/wiki/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getCode(): string
    {
        return 'wikivoyage';
    }

    public function getLabel(): string
    {
        return 'Wikivoyage';
    }

    public function discover(DestinationImportRequest $request): DestinationProviderResult
    {
        if ($request->cityName === null || $request->targetType !== DestinationEntityType::CITY) {
            return DestinationProviderResult::failure($this->getCode(), ['Wikivoyage district discovery requires a city target.']);
        }

        $page = $request->cityName;
        $url = self::API_URL . '?' . http_build_query([
            'action' => 'parse',
            'page' => $page,
            'prop' => 'text',
            'format' => 'json',
            'formatversion' => '2',
        ]);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'AlefBayeSafarBot/0.1 (+destination catalog import)',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ],
                'timeout' => 20,
            ]);
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            return DestinationProviderResult::failure($this->getCode(), [$exception->getMessage()], ['url' => $url]);
        }

        if ($statusCode >= 400) {
            return DestinationProviderResult::failure($this->getCode(), [sprintf('Provider returned HTTP %d.', $statusCode)], ['url' => $url]);
        }

        $data = json_decode($body, true);
        if (!\is_array($data) || isset($data['error']) || !isset($data['parse']['text']) || !\is_string($data['parse']['text'])) {
            return DestinationProviderResult::failure($this->getCode(), ['Wikivoyage API response did not contain parseable page text.'], ['url' => $url]);
        }

        $html = $data['parse']['text'];
        $sourceUrl = self::PAGE_BASE_URL . rawurlencode($page);
        $candidates = [
            new DestinationCandidate(
                provider: $this->getCode(),
                type: DestinationEntityType::COUNTRY,
                name: $request->countryName,
                sourceUrl: $sourceUrl,
                sourceTitle: $page,
                rawData: ['source_url' => $sourceUrl, 'page' => $page],
            ),
            new DestinationCandidate(
                provider: $this->getCode(),
                type: DestinationEntityType::CITY,
                name: $request->cityName,
                countryName: $request->countryName,
                sourceUrl: $sourceUrl,
                sourceTitle: $page,
                rawData: ['source_url' => $sourceUrl, 'page' => $page],
            ),
        ];

        foreach ($this->extractRegionListItems($html) as $item) {
            $candidates[] = new DestinationCandidate(
                provider: $this->getCode(),
                type: DestinationEntityType::DISTRICT,
                name: $item['name'],
                countryName: $request->countryName,
                cityName: $request->cityName,
                externalId: 'wikivoyage:' . $page . ':' . $item['href'],
                sourceUrl: $item['href'] !== '' ? 'https://en.wikivoyage.org' . $item['href'] : $sourceUrl,
                sourceTitle: $item['name'],
                rawData: [
                    'source_url' => $sourceUrl,
                    'page' => $page,
                    'href' => $item['href'],
                    'description' => $item['description'],
                ],
            );
        }

        if (\count($candidates) <= 2) {
            return DestinationProviderResult::failure($this->getCode(), ['No Wikivoyage district region list items were extracted.'], ['url' => $url]);
        }

        return DestinationProviderResult::success($this->getCode(), $candidates, [
            'url' => $url,
            'http_status' => $statusCode,
            'district_count' => \count($candidates) - 2,
        ]);
    }

    /**
     * @return array<int, array{name: string, href: string, description: string}>
     */
    private function extractRegionListItems(string $html): array
    {
        $items = [];
        if (preg_match_all('/<td class="regionlistitem-textholder">\s*<b><a href="([^"]*)"[^>]*>(.*?)<\/a><\/b>\s*<br\s*\/?>(.*?)<\/td>/isu', $html, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $name = $this->cleanText((string) $match[2]);
            $href = html_entity_decode((string) $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $description = $this->cleanText((string) $match[3]);
            if ($name === '' || mb_strlen($name) > 120) {
                continue;
            }

            $items[$href . ':' . mb_strtolower($name)] = [
                'name' => $name,
                'href' => $href,
                'description' => $description,
            ];
        }

        return array_values($items);
    }

    private function cleanText(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
