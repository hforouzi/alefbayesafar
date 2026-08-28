<?php

namespace App\Modules\Flight\Enum;

enum FlightPriceSourceType: string
{
    case OWN = 'own';
    case EXTERNAL = 'external';
}
