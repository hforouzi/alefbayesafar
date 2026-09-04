<?php

namespace App\Modules\Flight\Enum;

enum FlightTripType: string
{
    case ONE_WAY = 'one_way';
    case ROUND_TRIP = 'round_trip';

    /**
     * @return array<string, self>
     */
    public static function choices(): array
    {
        return [
            'flight.trip_type.one_way' => self::ONE_WAY,
            'flight.trip_type.round_trip' => self::ROUND_TRIP,
        ];
    }
}
