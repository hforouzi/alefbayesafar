<?php

namespace App\Modules\TripPlanner\Enum;

enum TripPlanningGoal: string
{
    case SPECIFIC_DESTINATION = 'specific_destination';
    case CHEAPEST = 'cheapest';
    case BEST_VALUE = 'best_value';
    case SURPRISE_ME = 'surprise_me';
}
