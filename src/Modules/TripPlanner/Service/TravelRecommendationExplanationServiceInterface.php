<?php

namespace App\Modules\TripPlanner\Service;

use App\Modules\TripPlanner\ValueObject\TravelRecommendationExplanation;
use App\Modules\TripPlanner\ValueObject\TripPlanResult;
use App\Modules\TripPlanner\ValueObject\TripSearchRequest;

/**
 * Produces a natural-language explanation of a trip plan result.
 *
 * Implementations must only summarize facts already present on the
 * TripSearchRequest / TripPlanResult (options, reasons, warnings, hotel
 * recommendation context). They must never invent prices, availability,
 * hotel reviews, visa facts, or market-wide claims.
 */
interface TravelRecommendationExplanationServiceInterface
{
    public function explain(TripSearchRequest $request, TripPlanResult $result): TravelRecommendationExplanation;
}
