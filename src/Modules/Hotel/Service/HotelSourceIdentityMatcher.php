<?php

namespace App\Modules\Hotel\Service;

use App\Modules\Hotel\Entity\Hotel;

final readonly class HotelSourceIdentityMatcher
{
    /**
     * @param array<string, mixed> $metadata
     *
     * @return array{match: bool, reason: string, canonicalTokens: string[], titleTokens: string[], urlTokens: string[]}
     */
    public function evaluate(Hotel $hotel, ?string $sourceTitle, ?string $sourceUrl, array $metadata = []): array
    {
        $canonicalTokens = $this->tokens($hotel->getName(), $hotel);
        if ($canonicalTokens === []) {
            return $this->result(false, 'canonical hotel name has no usable identity tokens', [], [], []);
        }

        $title = $sourceTitle ?? $this->metadataTitle($metadata);
        $titleTokens = $this->tokens((string) $title, $hotel);
        $urlTokens = $this->tokens($this->urlSlug($sourceUrl), $hotel);

        if ($titleTokens !== [] && $this->containsAll($titleTokens, $canonicalTokens)) {
            return $this->result(true, 'source title matched canonical hotel identity', $canonicalTokens, $titleTokens, $urlTokens);
        }

        if ($urlTokens !== []) {
            if ($this->containsAll($urlTokens, $canonicalTokens)) {
                return $this->result(true, 'source URL slug matched canonical hotel identity', $canonicalTokens, $titleTokens, $urlTokens);
            }

            return $this->result(false, 'source URL slug contradicted canonical hotel identity', $canonicalTokens, $titleTokens, $urlTokens);
        }

        if ($titleTokens !== []) {
            return $this->result(false, 'source title contradicted canonical hotel identity', $canonicalTokens, $titleTokens, $urlTokens);
        }

        return $this->result(false, 'source title/url did not match canonical hotel identity', $canonicalTokens, $titleTokens, $urlTokens);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function matches(Hotel $hotel, ?string $sourceTitle, ?string $sourceUrl, array $metadata = []): bool
    {
        return $this->evaluate($hotel, $sourceTitle, $sourceUrl, $metadata)['match'];
    }

    /**
     * @return string[]
     */
    private function tokens(string $value, Hotel $hotel): array
    {
        $value = strtolower($value);
        $value = preg_replace('/\([^)]*\)/u', ' ', $value) ?? $value;
        $value = $this->ascii($value);
        $value = preg_replace('/\b(updated|prices?|special|class|booking|com|hotel|hotels?|resort|apartments?|suites?|inn|the|and|with|official|website)\b/u', ' ', $value) ?? $value;
        $value = preg_replace('/\b20\d{2}\b/u', ' ', $value) ?? $value;

        foreach ($this->geographyWords($hotel) as $word) {
            $value = preg_replace('/\b' . preg_quote($word, '/') . '\b/u', ' ', $value) ?? $value;
        }

        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;
        $tokens = array_values(array_filter(
            explode(' ', trim($value)),
            static fn (string $token): bool => \strlen($token) >= 3,
        ));

        return array_values(array_unique($tokens));
    }

    private function ascii(string $value): string
    {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return \is_string($converted) && $converted !== '' ? $converted : $value;
    }

    /**
     * @return string[]
     */
    private function geographyWords(Hotel $hotel): array
    {
        $words = [
            $hotel->getCity()?->getName(),
            $hotel->getCity()?->getCountry()?->getName(),
        ];

        $tokens = [];
        foreach ($words as $word) {
            if ($word === null) {
                continue;
            }

            $normalized = preg_replace('/[^a-z0-9]+/u', ' ', $this->ascii(strtolower($word))) ?? '';
            foreach (explode(' ', trim($normalized)) as $token) {
                if (\strlen($token) >= 3) {
                    $tokens[] = $token;
                }
            }
        }

        return array_values(array_unique($tokens));
    }

    private function urlSlug(?string $url): string
    {
        if ($url === null) {
            return '';
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!\is_string($path) || $path === '') {
            return '';
        }

        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function metadataTitle(array $metadata): ?string
    {
        foreach (['title', 'og:title', 'sourceTitle', 'name'] as $key) {
            $value = $metadata[$key] ?? null;
            if (\is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    /**
     * @param string[] $candidateTokens
     * @param string[] $canonicalTokens
     */
    private function containsAll(array $candidateTokens, array $canonicalTokens): bool
    {
        foreach ($canonicalTokens as $token) {
            if (!\in_array($token, $candidateTokens, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string[] $canonicalTokens
     * @param string[] $titleTokens
     * @param string[] $urlTokens
     *
     * @return array{match: bool, reason: string, canonicalTokens: string[], titleTokens: string[], urlTokens: string[]}
     */
    private function result(bool $match, string $reason, array $canonicalTokens, array $titleTokens, array $urlTokens): array
    {
        return [
            'match' => $match,
            'reason' => $reason,
            'canonicalTokens' => $canonicalTokens,
            'titleTokens' => $titleTokens,
            'urlTokens' => $urlTokens,
        ];
    }
}
