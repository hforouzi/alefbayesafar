<?php

namespace App\Modules\Hotel\Provider;

use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\ValueObject\HotelOfferSearchRequest;
use App\Modules\Hotel\ValueObject\HotelOfferSearchResult;
use App\Modules\SearchSource\Entity\SearchSource;

interface HotelOfferProviderInterface
{
    public function supports(SearchSource $source): bool;

    public function search(SearchSource $source, Hotel $hotel, HotelOfferSearchRequest $request): HotelOfferSearchResult;
}
