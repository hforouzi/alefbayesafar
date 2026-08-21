<?php

namespace App\Modules\Hotel\Service;

use Symfony\Component\HttpFoundation\Request;

final readonly class HotelAdminListRequest
{
    public const DEFAULT_PAGE_SIZE = 25;
    public const MAX_PAGE_SIZE = 100;

    /**
     * @return array{q: string, name: string, nameFa: string, city: int|null, district: int|null, stars: string, active: string, verified: string, page: int, pageSize: int}
     */
    public function hotelFilters(Request $request): array
    {
        return $this->withPage($request, [
            'q' => $this->text($request, 'q'),
            'name' => $this->text($request, 'name'),
            'nameFa' => $this->text($request, 'nameFa'),
            'city' => $this->positiveInt($request, 'city'),
            'district' => $this->positiveInt($request, 'district'),
            'stars' => $this->stars($request),
            'active' => $this->boolean($request, 'active'),
            'verified' => $this->boolean($request, 'verified'),
        ]);
    }

    /**
     * @return array{q: string, name: string, nameFa: string, code: string, active: string, page: int, pageSize: int}
     */
    public function amenityFilters(Request $request): array
    {
        return $this->withPage($request, [
            'q' => $this->text($request, 'q'),
            'name' => $this->text($request, 'name'),
            'nameFa' => $this->text($request, 'nameFa'),
            'code' => $this->code($request, 'code', 120),
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
        return mb_substr(strtolower(trim((string) $request->query->get($name, ''))), 0, $maxLength);
    }

    private function positiveInt(Request $request, string $name): ?int
    {
        $value = $request->query->getInt($name, 0);

        return $value > 0 ? $value : null;
    }

    private function boolean(Request $request, string $name): string
    {
        $value = (string) $request->query->get($name, '');

        return \in_array($value, ['1', '0'], true) ? $value : '';
    }

    private function stars(Request $request): string
    {
        $value = (string) $request->query->get('stars', '');

        return \in_array($value, ['1', '2', '3', '4', '5'], true) ? $value : '';
    }
}
