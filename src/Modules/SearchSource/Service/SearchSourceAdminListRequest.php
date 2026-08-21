<?php

namespace App\Modules\SearchSource\Service;

use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use Symfony\Component\HttpFoundation\Request;

final readonly class SearchSourceAdminListRequest
{
    public const DEFAULT_PAGE_SIZE = 25;
    public const MAX_PAGE_SIZE = 100;

    /**
     * @return array{q: string, providerType: string, country: int|null, enabled: string, page: int, pageSize: int}
     */
    public function filters(Request $request): array
    {
        $providerType = (string) $request->query->get('providerType', '');
        if (!SearchSourceProviderType::tryFrom($providerType) instanceof SearchSourceProviderType) {
            $providerType = '';
        }

        return [
            'q' => mb_substr(trim((string) $request->query->get('q', '')), 0, 120),
            'providerType' => $providerType,
            'country' => $this->positiveInt($request, 'country'),
            'enabled' => $this->boolean($request, 'enabled'),
            'page' => max(1, $request->query->getInt('page', 1)),
            'pageSize' => $this->pageSize($request),
        ];
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

    private function pageSize(Request $request): int
    {
        $pageSize = $request->query->getInt('pageSize', $request->query->getInt('perPage', self::DEFAULT_PAGE_SIZE));

        return max(1, min(self::MAX_PAGE_SIZE, $pageSize));
    }
}
