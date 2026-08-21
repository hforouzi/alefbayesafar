<?php

namespace App\Modules\Hotel\Provider;

use App\Modules\Hotel\ValueObject\HotelSearchRequest;
use App\Modules\Hotel\ValueObject\HotelSearchResult;
use App\Modules\SearchSource\Entity\SearchSource;

interface HotelSearchProviderInterface
{
    public function supports(SearchSource $source): bool;

    public function search(SearchSource $source, HotelSearchRequest $request): HotelSearchResult;
}
