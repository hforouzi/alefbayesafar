<?php

namespace App\Modules\Hotel\Enum;

enum HotelOfferSearchStatus: string
{
    case OFFERS_FOUND = 'offers_found';
    case SOLD_OUT = 'sold_out';
    case NO_DATA = 'no_data';
    case PROVIDER_ERROR = 'provider_error';

    public function isProviderFailure(): bool
    {
        return $this === self::PROVIDER_ERROR;
    }
}
