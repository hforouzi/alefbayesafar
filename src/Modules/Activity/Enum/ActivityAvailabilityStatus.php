<?php

namespace App\Modules\Activity\Enum;

enum ActivityAvailabilityStatus: string
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
            'activity.availability_status.available' => self::AVAILABLE,
            'activity.availability_status.unavailable' => self::UNAVAILABLE,
            'activity.availability_status.unknown' => self::UNKNOWN,
        ];
    }
}
