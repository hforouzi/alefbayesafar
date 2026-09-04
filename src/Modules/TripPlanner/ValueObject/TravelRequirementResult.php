<?php

namespace App\Modules\TripPlanner\ValueObject;

final readonly class TravelRequirementResult
{
    /**
     * @param TravelRequirementItem[] $requirements
     * @param string[] $warnings
     */
    public function __construct(
        public array $requirements = [],
        public array $warnings = [],
    ) {
    }
}
