<?php

namespace App\Modules\Flight\Enum;

enum FlightCabinClass: string
{
    case ECONOMY = 'economy';
    case PREMIUM_ECONOMY = 'premium_economy';
    case BUSINESS = 'business';
    case FIRST = 'first';

    /**
     * @return array<string, self>
     */
    public static function choices(): array
    {
        return [
            'flight.cabin_class.economy' => self::ECONOMY,
            'flight.cabin_class.premium_economy' => self::PREMIUM_ECONOMY,
            'flight.cabin_class.business' => self::BUSINESS,
            'flight.cabin_class.first' => self::FIRST,
        ];
    }
}
