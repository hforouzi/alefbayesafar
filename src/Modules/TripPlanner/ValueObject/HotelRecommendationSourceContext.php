<?php

namespace App\Modules\TripPlanner\ValueObject;

final readonly class HotelRecommendationSourceContext
{
    /**
     * @param array<string, string> $reviewSignals
     * @param string[] $attributes
     * @param string[] $warnings
     */
    public function __construct(
        public string $source,
        public string $status,
        public ?string $sourceHotelId = null,
        public ?string $sourceUrl = null,
        public ?string $reviewScore = null,
        public ?string $reviewScoreScale = null,
        public ?int $reviewCount = null,
        public array $reviewSignals = [],
        public array $attributes = [],
        public ?\DateTimeImmutable $fetchedAt = null,
        public string $freshness = 'unknown',
        public bool $liveRefreshAttempted = false,
        public ?string $liveRefreshStatus = null,
        public array $warnings = [],
    ) {
    }
}
