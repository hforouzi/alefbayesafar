<?php

namespace App\Modules\Tour\Service;

use Symfony\Component\HttpFoundation\Request;

final readonly class TourAdminListRequest
{
    /**
     * @return array{q: string, destination: int|null, active: string, featured: string, publicVisible: string, page: int, pageSize: int}
     */
    public function packageFilters(Request $request): array
    {
        $pageSize = (int) $request->query->get('pageSize', 20);
        $pageSize = \in_array($pageSize, [10, 20, 50, 100], true) ? $pageSize : 20;

        $destination = $request->query->get('destination');

        return [
            'q' => trim((string) $request->query->get('q', '')),
            'destination' => $destination !== null && $destination !== '' ? (int) $destination : null,
            'active' => trim((string) $request->query->get('active', '')),
            'featured' => trim((string) $request->query->get('featured', '')),
            'publicVisible' => trim((string) $request->query->get('publicVisible', '')),
            'page' => max(1, (int) $request->query->get('page', 1)),
            'pageSize' => $pageSize,
        ];
    }
}
