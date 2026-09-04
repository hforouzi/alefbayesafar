<?php

namespace App\Modules\TripPlanner\Service;

use App\Modules\TripPlanner\Enum\PlanningReadinessStatus;
use App\Modules\TripPlanner\ValueObject\PlanningClarificationResult;
use App\Modules\TripPlanner\ValueObject\TripSearchRequest;

final readonly class PlanningIntentClarifier
{
    public function evaluate(TripSearchRequest $request): PlanningClarificationResult
    {
        $missing = [];
        if ($request->adults < 1) {
            $missing[] = 'travelers';
        }
        if ($request->requiresSpecificDestination() && !$request->hasDestinationScope()) {
            $missing[] = 'destination';
        }
        if ($request->isFlexible() && $request->nights === null) {
            $missing[] = 'nights';
        }

        if ($missing !== []) {
            return new PlanningClarificationResult(PlanningReadinessStatus::NEEDS_CLARIFICATION, $missing, $this->questionKey($missing[0]));
        }

        return new PlanningClarificationResult(PlanningReadinessStatus::READY_TO_SEARCH);
    }

    private function questionKey(string $field): string
    {
        return match ($field) {
            'destination' => 'trip_planner.question.destination',
            'nights' => 'trip_planner.question.nights',
            'travelers' => 'trip_planner.question.travelers',
            default => 'trip_planner.question.next',
        };
    }
}
