<?php

namespace App\Modules\TripPlanner\Enum;

enum TravelRequirementType: string
{
    case VISA = 'visa';
    case PASSPORT_VALIDITY = 'passport_validity';
    case TRANSIT = 'transit';
    case INSURANCE = 'insurance';
    case DRIVING = 'driving';
    case BORDER = 'border';
    case VEHICLE_DOCUMENT = 'vehicle_document';
    case FERRY = 'ferry';
    case OTHER = 'other';
}
