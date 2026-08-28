<?php

namespace App\Modules\Flight\Enum;

enum FlightAvailabilityStatus: string
{
    case AVAILABLE = 'available';
    case UNAVAILABLE = 'unavailable';
    case UNKNOWN = 'unknown';

    /**
     * @return array<string, self>
     */
    public static function choices(): array
    {
        return [
            'flight.availability.available' => self::AVAILABLE,
            'flight.availability.unavailable' => self::UNAVAILABLE,
            'flight.availability.unknown' => self::UNKNOWN,
        ];
    }
}
