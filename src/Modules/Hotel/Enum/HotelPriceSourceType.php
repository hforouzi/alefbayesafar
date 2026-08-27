<?php

namespace App\Modules\Hotel\Enum;

enum HotelPriceSourceType: string
{
    case OWN = 'own';
    case EXTERNAL = 'external';
}
