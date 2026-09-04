<?php

namespace App\Modules\TripPlanner\Enum;

enum TripPlanStatus: string
{
    case OPTIONS_FOUND = 'options_found';
    case PARTIAL_OPTIONS = 'partial_options';
    case NO_OPTIONS = 'no_options';
    case INVALID_REQUEST = 'invalid_request';
}
