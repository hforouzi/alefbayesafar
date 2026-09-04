<?php

namespace App\Modules\TripPlanner\ValueObject;

use App\Modules\TripPlanner\Enum\PlanningReadinessStatus;

final readonly class PlanningClarificationResult
{
    /**
     * @param string[] $missingCriticalFields
     */
    public function __construct(
        public PlanningReadinessStatus $status,
        public array $missingCriticalFields = [],
        public ?string $suggestedNextQuestionKey = null,
    ) {
    }

    public function isReady(): bool
    {
        return $this->status === PlanningReadinessStatus::READY_TO_SEARCH;
    }
}
