<?php

namespace App\Modules\Destination\Provider;

use App\Modules\Destination\ValueObject\DestinationInsightResult;

/**
 * Provider-neutral boundary for real, factual destination-advice data
 * (shopping malls, markets, attractions, restaurants, museums, ...).
 *
 * Implementations must only return facts an actual external source
 * returned. They must never fabricate places, ratings or review counts.
 */
interface DestinationInsightProviderInterface
{
    public function getCode(): string;

    public function search(string $cityName, ?string $countryName, string $category, int $limit): DestinationInsightResult;
}
