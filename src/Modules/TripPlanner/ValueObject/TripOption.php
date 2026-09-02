<?php

namespace App\Modules\TripPlanner\ValueObject;

use App\Modules\TripPlanner\Enum\TripOptionType;

final readonly class TripOption
{
    /**
     * @param TripComponentSummary[] $components
     * @param string[] $reasons
     * @param string[] $warnings
     */
    public function __construct(
        public TripOptionType $optionType,
        public string $sourceType,
        public ?string $sourceName,
        public string $title,
        public ?string $currency,
        public ?string $totalPrice,
        public array $components,
        public \DateTimeImmutable $departureDate,
        public ?\DateTimeImmutable $returnDate,
        public int $nights,
        public string $completenessStatus,
        public string $budgetStatus,
        public int $rankingScore,
        public array $reasons,
        public array $warnings,
        public ?string $bookingUrl = null,
    ) {
    }

    public function isComplete(): bool
    {
        return $this->completenessStatus === 'complete';
    }
}
