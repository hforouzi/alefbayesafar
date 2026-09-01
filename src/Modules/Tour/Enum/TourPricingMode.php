<?php

namespace App\Modules\Tour\Enum;

enum TourPricingMode: string
{
    case PER_PASSENGER_TYPE = 'per_passenger_type';
    case TOTAL_PARTY = 'total_party';

    /**
     * @return array<string, self>
     */
    public static function choices(): array
    {
        return [
            'tour.pricing_mode.per_passenger_type' => self::PER_PASSENGER_TYPE,
            'tour.pricing_mode.total_party' => self::TOTAL_PARTY,
        ];
    }
}
