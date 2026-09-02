<?php

namespace App\Modules\Transfer\Service;

use Symfony\Component\HttpFoundation\Request;

final readonly class TransferAdminListRequest
{
    /**
     * @return array{q: string, transferType: string, active: string, featured: string, publicVisible: string, page: int, pageSize: int}
     */
    public function productFilters(Request $request): array
    {
        $pageSize = (int) $request->query->get('pageSize', 20);
        $pageSize = \in_array($pageSize, [10, 20, 50, 100], true) ? $pageSize : 20;

        return [
            'q' => trim((string) $request->query->get('q', '')),
            'transferType' => trim((string) $request->query->get('transferType', '')),
            'active' => trim((string) $request->query->get('active', '')),
            'featured' => trim((string) $request->query->get('featured', '')),
            'publicVisible' => trim((string) $request->query->get('publicVisible', '')),
            'page' => max(1, (int) $request->query->get('page', 1)),
            'pageSize' => $pageSize,
        ];
    }
}
