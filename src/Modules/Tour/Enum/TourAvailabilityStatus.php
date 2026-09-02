<?php

namespace App\Modules\Tour\Enum;

enum TourAvailabilityStatus: string
{
    case AVAILABLE = 'available';
    case UNAVAILABLE = 'unavailable';
    case UNKNOWN = 'unknown';
}
