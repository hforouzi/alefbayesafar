<?php

namespace App\Modules\Tour\Enum;

enum TourSourceEligibilityStatus: string
{
    case ELIGIBLE = 'eligible';
    case INELIGIBLE = 'ineligible';
    case UNKNOWN = 'unknown';
}
