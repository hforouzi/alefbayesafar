<?php

namespace App\Modules\TripPlanner\ValueObject;

use App\Modules\TripPlanner\Enum\TravelRequirementType;

final readonly class TravelRequirementItem
{
    /**
     * @param string[] $transitCountries
     */
    public function __construct(
        public TravelRequirementType $type,
        public string $summary,
        public string $source,
        public \DateTimeImmutable $checkedAt,
        public ?string $originNationality = null,
        public ?string $destination = null,
        public array $transitCountries = [],
    ) {
    }
}
