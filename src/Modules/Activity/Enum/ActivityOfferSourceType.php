<?php

namespace App\Modules\Activity\Enum;

enum ActivityOfferSourceType: string
{
    case OWN = 'own';
    case EXTERNAL = 'external';
}
