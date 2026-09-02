<?php

namespace App\Modules\Activity\Service;

use Symfony\Component\HttpFoundation\Request;

final readonly class ActivityAdminListRequest
{
    /**
     * @return array{q: string, city: int|null, category: string, active: string, featured: string, publicVisible: string, page: int, pageSize: int}
     */
    public function activityFilters(Request $request): array
    {
        $pageSize = (int) $request->query->get('pageSize', 20);
        $pageSize = \in_array($pageSize, [10, 20, 50, 100], true) ? $pageSize : 20;

        $city = $request->query->get('city');

        return [
            'q' => trim((string) $request->query->get('q', '')),
            'city' => $city !== null && $city !== '' ? (int) $city : null,
            'category' => trim((string) $request->query->get('category', '')),
            'active' => trim((string) $request->query->get('active', '')),
            'featured' => trim((string) $request->query->get('featured', '')),
            'publicVisible' => trim((string) $request->query->get('publicVisible', '')),
            'page' => max(1, (int) $request->query->get('page', 1)),
            'pageSize' => $pageSize,
        ];
    }
}
