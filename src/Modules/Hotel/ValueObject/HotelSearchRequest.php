<?php

namespace App\Modules\Hotel\ValueObject;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\District;

final readonly class HotelSearchRequest
{
    public function __construct(
        public string $query,
        public City $city,
        public ?District $district = null,
        public int $limit = 10,
    ) {
    }
}
