<?php

namespace App\Modules\Destination\Service;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Entity\State;
use App\Modules\Destination\Repository\CityRepository;
use App\Modules\Destination\Repository\CountryRepository;
use App\Modules\Destination\Repository\StateRepository;

final readonly class AdminFilterLabelResolver
{
    public function __construct(
        private CountryRepository $countryRepository,
        private StateRepository $stateRepository,
        private CityRepository $cityRepository,
    ) {
    }

    /**
     * @param array<string, scalar|null> $filters
     *
     * @return array{country?: string, state?: string, city?: string}
     */
    public function labels(array $filters): array
    {
        $labels = [];

        if (isset($filters['country']) && \is_int($filters['country'])) {
            $country = $this->countryRepository->find($filters['country']);
            if ($country instanceof Country) {
                $labels['country'] = (string) $country;
            }
        }

        if (isset($filters['state']) && \is_int($filters['state'])) {
            $state = $this->stateRepository->find($filters['state']);
            if ($state instanceof State) {
                $labels['state'] = (string) $state;
            }
        }

        if (isset($filters['city']) && \is_int($filters['city'])) {
            $city = $this->cityRepository->find($filters['city']);
            if ($city instanceof City) {
                $labels['city'] = (string) $city;
            }
        }

        return $labels;
    }
}
