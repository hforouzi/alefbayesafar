<?php

namespace App\Modules\TripPlanner\Enum;

enum TripDateMode: string
{
    case EXACT = 'exact';
    case FLEXIBLE = 'flexible';
}
