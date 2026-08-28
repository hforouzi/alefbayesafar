<?php

namespace App\Modules\Flight\Provider;

use App\Modules\Flight\ValueObject\FlightOfferSearchRequest;
use App\Modules\Flight\ValueObject\FlightOfferSearchResult;
use App\Modules\SearchSource\Entity\SearchSource;

interface FlightOfferProviderInterface
{
    public function supports(SearchSource $source): bool;

    public function search(SearchSource $source, FlightOfferSearchRequest $request): FlightOfferSearchResult;
}
