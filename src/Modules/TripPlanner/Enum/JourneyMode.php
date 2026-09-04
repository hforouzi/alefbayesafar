<?php

namespace App\Modules\TripPlanner\Enum;

enum JourneyMode: string
{
    case FLIGHT = 'flight';
    case GROUND_TRANSFER = 'ground_transfer';
    case BUS = 'bus';
    case TRAIN = 'train';
    case FERRY = 'ferry';
    case SELF_DRIVE = 'self_drive';
    case OTHER = 'other';
}
