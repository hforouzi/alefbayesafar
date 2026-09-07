<?php

namespace App\Modules\TripPlanner\Service;

use App\Modules\Destination\Provider\DestinationInsightProviderInterface;
use App\Modules\Destination\ValueObject\DestinationInsightResult;

/**
 * Bridges the conversational planner to DestinationInsightProviderInterface.
 *
 * Never invents insights when the provider fails or the purpose is not
 * (yet) a supported category — it returns an honest empty/failure result
 * instead, so the caller can say "no data" rather than fabricate one.
 */
final readonly class DestinationInsightService
{
    private const DEFAULT_LIMIT = 5;

    public function __construct(private DestinationInsightProviderInterface $provider)
    {
    }

    public function forPurpose(string $cityName, ?string $countryName, string $purpose, int $limit = self::DEFAULT_LIMIT): DestinationInsightResult
    {
        try {
            return $this->provider->search($cityName, $countryName, $purpose, $limit);
        } catch (\Throwable $exception) {
            return DestinationInsightResult::failure([$exception->getMessage()]);
        }
    }
}
