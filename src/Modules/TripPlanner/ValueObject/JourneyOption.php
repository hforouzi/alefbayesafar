<?php

namespace App\Modules\TripPlanner\ValueObject;

final readonly class JourneyOption
{
    /**
     * @param JourneyLeg[] $legs
     * @param string[] $reasons
     * @param string[] $warnings
     */
    public function __construct(
        public string $strategyCode,
        public string $title,
        public array $legs,
        public bool $fullyVerified,
        public array $reasons = [],
        public array $warnings = [],
    ) {
    }
}
