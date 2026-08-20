<?php

namespace App\Modules\Destination\Provider;

use App\Modules\Destination\ValueObject\DestinationImportRequest;

final class BookingDestinationProvider extends AbstractPublicWebDestinationProvider
{
    public function getCode(): string
    {
        return 'booking';
    }

    public function getLabel(): string
    {
        return 'Booking';
    }

    protected function buildUrl(DestinationImportRequest $request): string
    {
        $query = trim(($request->cityName ?? '') . ' ' . $request->countryName);

        return 'https://www.booking.com/searchresults.html?' . http_build_query([
            'ss' => $query,
            'lang' => 'en-us',
        ]);
    }

    protected function extractAreaNames(string $html, DestinationImportRequest $request): array
    {
        $names = [];
        $patterns = [
            '/data-testid="filters-group-label-content"[^>]*>(.*?)<\/[^>]+>/isu',
            '/<label[^>]*>([^<]*(?:Area|District|Neighborhood|Neighbourhood|Square)[^<]*)<\/label>/isu',
            '/"name"\s*:\s*"([^"]+(?:Area|District|Neighborhood|Neighbourhood|Square)[^"]*)"/isu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches) === false) {
                continue;
            }

            foreach ($matches[1] as $match) {
                $name = trim(html_entity_decode(strip_tags((string) $match), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($this->looksLikeArea($name, $request)) {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    private function looksLikeArea(string $name, DestinationImportRequest $request): bool
    {
        if ($name === '' || mb_strlen($name) > 80) {
            return false;
        }

        $lower = mb_strtolower($name);
        if ($request->cityName !== null && $lower === mb_strtolower($request->cityName)) {
            return false;
        }

        return !str_contains($lower, 'hotel') && !str_contains($lower, 'property');
    }
}
