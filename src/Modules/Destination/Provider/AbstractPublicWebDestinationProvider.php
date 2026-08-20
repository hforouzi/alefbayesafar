<?php

namespace App\Modules\Destination\Provider;

use App\Modules\Destination\ValueObject\DestinationCandidate;
use App\Modules\Destination\ValueObject\DestinationEntityType;
use App\Modules\Destination\ValueObject\DestinationImportRequest;
use App\Modules\Destination\ValueObject\DestinationProviderResult;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractPublicWebDestinationProvider implements DestinationProviderInterface
{
    public function __construct(protected readonly HttpClientInterface $httpClient)
    {
    }

    public function discover(DestinationImportRequest $request): DestinationProviderResult
    {
        $url = $this->buildUrl($request);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'AlefBayeSafarBot/0.1 (+destination catalog import)',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ],
                'timeout' => 15,
            ]);
            $statusCode = $response->getStatusCode();
            $html = $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            return DestinationProviderResult::failure($this->getCode(), [$exception->getMessage()], ['url' => $url]);
        }

        if ($statusCode >= 400) {
            return DestinationProviderResult::failure($this->getCode(), [sprintf('Provider returned HTTP %d.', $statusCode)], ['url' => $url]);
        }

        if ($this->looksLikeAccessChallenge($html)) {
            return DestinationProviderResult::failure($this->getCode(), ['Provider returned a JavaScript/WAF challenge instead of destination content.'], [
                'url' => $url,
                'http_status' => $statusCode,
            ]);
        }

        $candidates = $this->extractCandidates($html, $request, $url);
        if ($candidates === []) {
            return DestinationProviderResult::failure($this->getCode(), ['No destination candidates could be extracted from the provider response.'], [
                'url' => $url,
                'http_status' => $statusCode,
            ]);
        }

        return DestinationProviderResult::success($this->getCode(), $candidates, [
            'url' => $url,
            'http_status' => $statusCode,
        ]);
    }

    abstract protected function buildUrl(DestinationImportRequest $request): string;

    protected function looksLikeAccessChallenge(string $html): bool
    {
        $lower = mb_strtolower($html);

        return str_contains($lower, 'awswaf')
            || str_contains($lower, '/__challenge_')
            || str_contains($lower, 'not a robot')
            || str_contains($lower, 'enable javascript');
    }

    /**
     * @return DestinationCandidate[]
     */
    protected function extractCandidates(string $html, DestinationImportRequest $request, string $url): array
    {
        $candidates = [];

        if ($request->targetType === DestinationEntityType::COUNTRY || $request->targetType === DestinationEntityType::CITY) {
            $candidates[] = new DestinationCandidate(
                provider: $this->getCode(),
                type: DestinationEntityType::COUNTRY,
                name: $request->countryName,
                sourceUrl: $url,
                sourceTitle: $this->extractTitle($html),
                rawData: ['source_url' => $url]
            );
        }

        if ($request->cityName !== null) {
            $candidates[] = new DestinationCandidate(
                provider: $this->getCode(),
                type: DestinationEntityType::CITY,
                name: $request->cityName,
                countryName: $request->countryName,
                sourceUrl: $url,
                sourceTitle: $this->extractTitle($html),
                rawData: ['source_url' => $url]
            );
        }

        foreach ($this->extractAreaNames($html, $request) as $areaName) {
            $candidates[] = new DestinationCandidate(
                provider: $this->getCode(),
                type: DestinationEntityType::DISTRICT,
                name: $areaName,
                countryName: $request->countryName,
                cityName: $request->cityName,
                sourceUrl: $url,
                sourceTitle: $areaName,
                rawData: ['source_url' => $url, 'source_area_name' => $areaName]
            );
        }

        return $this->deduplicateCandidates($candidates);
    }

    /**
     * @return string[]
     */
    abstract protected function extractAreaNames(string $html, DestinationImportRequest $request): array;

    protected function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match) === 1) {
            return trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return null;
    }

    /**
     * @param DestinationCandidate[] $candidates
     * @return DestinationCandidate[]
     */
    private function deduplicateCandidates(array $candidates): array
    {
        $seen = [];
        $deduplicated = [];
        foreach ($candidates as $candidate) {
            $key = $candidate->type . ':' . mb_strtolower($candidate->name);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduplicated[] = $candidate;
        }

        return $deduplicated;
    }
}
