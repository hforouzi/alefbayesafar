<?php

namespace App\Modules\Transfer\Enum;

enum TransferOfferSourceType: string
{
    case OWN = 'own';
    case EXTERNAL = 'external';
}
