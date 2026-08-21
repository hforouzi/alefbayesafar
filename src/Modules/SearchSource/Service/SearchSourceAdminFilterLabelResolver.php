<?php

namespace App\Modules\SearchSource\Service;

use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Repository\CountryRepository;

final readonly class SearchSourceAdminFilterLabelResolver
{
    public function __construct(private CountryRepository $countryRepository)
    {
    }

    /**
     * @param array{country?: int|null} $filters
     *
     * @return array{country?: string}
     */
    public function labels(array $filters): array
    {
        $labels = [];
        if (($filters['country'] ?? null) !== null) {
            $country = $this->countryRepository->find((int) $filters['country']);
            if ($country instanceof Country) {
                $labels['country'] = sprintf('%s%s', $country->getName(), $country->getIso2() !== null ? ' - ' . $country->getIso2() : '');
            }
        }

        return $labels;
    }
}
