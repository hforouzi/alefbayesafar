<?php

namespace App\Modules\Flight\Enum;

enum FlightDirection: string
{
    case OUTBOUND = 'outbound';
    case INBOUND = 'inbound';

    /**
     * @return array<string, self>
     */
    public static function choices(): array
    {
        return [
            'flight.direction.outbound' => self::OUTBOUND,
            'flight.direction.inbound' => self::INBOUND,
        ];
    }
}
