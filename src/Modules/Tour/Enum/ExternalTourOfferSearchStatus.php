<?php

namespace App\Modules\Tour\Enum;

enum ExternalTourOfferSearchStatus: string
{
    case OFFERS_FOUND = 'offers_found';
    case NO_RESULTS = 'no_results';
    case NO_DATA = 'no_data';
    case PROVIDER_ERROR = 'provider_error';
    case SKIPPED = 'skipped';

    public function isProviderFailure(): bool
    {
        return $this === self::PROVIDER_ERROR;
    }
}
