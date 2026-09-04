<?php

namespace App\Modules\TripPlanner\Provider;

use App\Modules\TripPlanner\ValueObject\JourneyOption;
use App\Modules\TripPlanner\ValueObject\TravelRequirementResult;
use App\Modules\TripPlanner\ValueObject\TripSearchRequest;

interface TravelRequirementProviderInterface
{
    public function supports(TripSearchRequest $request, JourneyOption $journeyOption): bool;

    public function check(TripSearchRequest $request, JourneyOption $journeyOption): TravelRequirementResult;
}
