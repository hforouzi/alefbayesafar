<?php

namespace App\Modules\Flight\Enum;

enum FlightOfferSearchStatus: string
{
    case OFFERS_FOUND = 'offers_found';
    case NO_RESULTS = 'no_results';
    case NO_DATA = 'no_data';
    case PROVIDER_ERROR = 'provider_error';

    public function isProviderFailure(): bool
    {
        return $this === self::PROVIDER_ERROR;
    }
}
