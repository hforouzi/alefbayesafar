<?php

namespace App\Modules\Flight\Service;

use Symfony\Component\HttpFoundation\Request;

final readonly class FlightAdminListRequest
{
    public const DEFAULT_PAGE_SIZE = 25;
    public const MAX_PAGE_SIZE = 100;

    /**
     * @return array{q: string, name: string, nameFa: string, iata: string, icao: string, active: string, page: int, pageSize: int}
     */
    public function airlineFilters(Request $request): array
    {
        return $this->withPage($request, [
            'q' => $this->text($request, 'q'),
            'name' => $this->text($request, 'name'),
            'nameFa' => $this->text($request, 'nameFa'),
            'iata' => $this->code($request, 'iata', 3),
            'icao' => $this->code($request, 'icao', 4),
            'active' => $this->boolean($request, 'active'),
        ]);
    }

    /**
     * @return array{q: string, tripType: string, cabinClass: string, active: string, page: int, pageSize: int}
     */
    public function ownFlightDealFilters(Request $request): array
    {
        return $this->withPage($request, [
            'q' => $this->text($request, 'q'),
            'tripType' => $this->choice($request, 'tripType', ['one_way', 'round_trip']),
            'cabinClass' => $this->choice($request, 'cabinClass', ['economy', 'premium_economy', 'business', 'first']),
            'active' => $this->boolean($request, 'active'),
        ]);
    }

    /**
     * @param array<string, scalar|null> $filters
     *
     * @return array<string, scalar|null>
     */
    private function withPage(Request $request, array $filters): array
    {
        return $filters + [
            'page' => max(1, $request->query->getInt('page', 1)),
            'pageSize' => $this->pageSize($request),
        ];
    }

    private function pageSize(Request $request): int
    {
        $pageSize = $request->query->getInt('pageSize', $request->query->getInt('perPage', self::DEFAULT_PAGE_SIZE));

        return max(1, min(self::MAX_PAGE_SIZE, $pageSize));
    }

    private function text(Request $request, string $name): string
    {
        return mb_substr(trim((string) $request->query->get($name, '')), 0, 120);
    }

    private function code(Request $request, string $name, int $maxLength): string
    {
        return mb_substr(strtoupper(trim((string) $request->query->get($name, ''))), 0, $maxLength);
    }

    /**
     * @param string[] $allowed
     */
    private function choice(Request $request, string $name, array $allowed): string
    {
        $value = (string) $request->query->get($name, '');

        return \in_array($value, $allowed, true) ? $value : '';
    }

    private function boolean(Request $request, string $name): string
    {
        $value = (string) $request->query->get($name, '');

        return \in_array($value, ['1', '0'], true) ? $value : '';
    }
}
