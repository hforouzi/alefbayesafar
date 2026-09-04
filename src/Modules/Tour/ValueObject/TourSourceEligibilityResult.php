<?php

namespace App\Modules\Tour\ValueObject;

use App\Modules\Tour\Enum\TourSourceEligibilityStatus;

final readonly class TourSourceEligibilityResult
{
    /**
     * @param string[] $reasons
     */
    public function __construct(
        public TourSourceEligibilityStatus $status,
        public array $reasons = [],
    ) {
    }

    public function shouldSearch(): bool
    {
        return $this->status !== TourSourceEligibilityStatus::INELIGIBLE;
    }
}
