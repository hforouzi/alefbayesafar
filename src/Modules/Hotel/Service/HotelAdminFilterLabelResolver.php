<?php

namespace App\Modules\Hotel\Service;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\District;
use App\Modules\Destination\Repository\CityRepository;
use App\Modules\Destination\Repository\DistrictRepository;

final readonly class HotelAdminFilterLabelResolver
{
    public function __construct(
        private CityRepository $cityRepository,
        private DistrictRepository $districtRepository,
    ) {
    }

    /**
     * @param array<string, scalar|null> $filters
     *
     * @return array{city?: string, district?: string}
     */
    public function labels(array $filters): array
    {
        $labels = [];

        if (isset($filters['city']) && \is_int($filters['city'])) {
            $city = $this->cityRepository->find($filters['city']);
            if ($city instanceof City) {
                $labels['city'] = (string) $city;
            }
        }

        if (isset($filters['district']) && \is_int($filters['district'])) {
            $district = $this->districtRepository->find($filters['district']);
            if ($district instanceof District) {
                $labels['district'] = (string) $district;
            }
        }

        return $labels;
    }
}
