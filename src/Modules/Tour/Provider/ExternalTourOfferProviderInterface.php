<?php

namespace App\Modules\Tour\Provider;

use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchRequest;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchResult;

interface ExternalTourOfferProviderInterface
{
    public function supports(SearchSource $source): bool;

    public function search(SearchSource $source, ExternalTourOfferSearchRequest $request): ExternalTourOfferSearchResult;
}
