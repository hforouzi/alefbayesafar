<?php

namespace App\Modules\TripPlanner\Enum;

enum TripOptionType: string
{
    case OUR_TOUR = 'our_tour';
    case EXTERNAL_TOUR = 'external_tour';
    case CUSTOM_COMBINATION = 'custom_combination';
}
