<?php

namespace App\Modules\TripPlanner\ValueObject;

use App\Modules\TripPlanner\Enum\TripPlanStatus;

final readonly class TripPlanResult
{
    /**
     * @param TripOption[] $options
     * @param string[] $messages
     * @param array<string, int> $counts
     * @param array<string, mixed> $diagnostics
     */
    public function __construct(
        public TripPlanStatus $status,
        public array $options,
        public array $messages,
        public array $counts,
        public array $diagnostics = [],
    ) {
        if ($this->options === [] && $this->messages === []) {
            throw new \InvalidArgumentException('Trip planner results must include a visible message when no options are available.');
        }
    }
}
