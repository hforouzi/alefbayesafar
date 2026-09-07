<?php

namespace App\Modules\TripPlanner\ValueObject;

final readonly class TravelRecommendationExplanation
{
    /**
     * @param array<int, string> $perOptionText keyed by TripOption::$rank
     */
    public function __construct(
        public string $overallText,
        public array $perOptionText = [],
    ) {
    }

    public function forOption(int $rank): ?string
    {
        return $this->perOptionText[$rank] ?? null;
    }
}
