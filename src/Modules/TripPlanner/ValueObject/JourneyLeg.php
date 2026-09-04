<?php

namespace App\Modules\TripPlanner\ValueObject;

use App\Modules\TripPlanner\Enum\JourneyMode;

final readonly class JourneyLeg
{
    /**
     * @param string[] $warnings
     */
    public function __construct(
        public JourneyMode $mode,
        public string $origin,
        public string $destination,
        public bool $verifiedInventory = false,
        public ?string $summary = null,
        public array $warnings = [],
    ) {
    }
}
