<?php

namespace App\Modules\TripPlanner\Enum;

enum PlanningReadinessStatus: string
{
    case READY_TO_SEARCH = 'ready_to_search';
    case NEEDS_CLARIFICATION = 'needs_clarification';
}
