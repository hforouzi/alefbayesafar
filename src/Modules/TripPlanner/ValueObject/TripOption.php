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
        public ?string $destinationCountry = null,
        public ?string $destinationCity = null,
        public ?string $hotelName = null,
        public ?string $hotelGrade = null,
        public ?string $board = null,
        public ?string $airline = null,
        public ?string $agency = null,
        public ?HotelRecommendationContext $hotelRecommendationContext = null,
        public int $rank = 0,
    ) {
    }

    public function isComplete(): bool
    {
        return $this->completenessStatus === 'complete';
    }

    public function withRank(int $rank): self
    {
        return new self(
            optionType: $this->optionType,
            sourceType: $this->sourceType,
            sourceName: $this->sourceName,
            title: $this->title,
            currency: $this->currency,
            totalPrice: $this->totalPrice,
            components: $this->components,
            departureDate: $this->departureDate,
            returnDate: $this->returnDate,
            nights: $this->nights,
            completenessStatus: $this->completenessStatus,
            budgetStatus: $this->budgetStatus,
            rankingScore: $this->rankingScore,
            reasons: $this->reasons,
            warnings: $this->warnings,
            bookingUrl: $this->bookingUrl,
            destinationCountry: $this->destinationCountry,
            destinationCity: $this->destinationCity,
            hotelName: $this->hotelName,
            hotelGrade: $this->hotelGrade,
            board: $this->board,
            airline: $this->airline,
            agency: $this->agency,
            hotelRecommendationContext: $this->hotelRecommendationContext,
            rank: $rank,
        );
    }

    public function withHotelRecommendationContext(?HotelRecommendationContext $context): self
    {
        return new self(
            optionType: $this->optionType,
            sourceType: $this->sourceType,
            sourceName: $this->sourceName,
            title: $this->title,
            currency: $this->currency,
            totalPrice: $this->totalPrice,
            components: $this->components,
            departureDate: $this->departureDate,
            returnDate: $this->returnDate,
            nights: $this->nights,
            completenessStatus: $this->completenessStatus,
            budgetStatus: $this->budgetStatus,
            rankingScore: $this->rankingScore,
            reasons: $this->reasons,
            warnings: $this->warnings,
            bookingUrl: $this->bookingUrl,
            destinationCountry: $this->destinationCountry,
            destinationCity: $this->destinationCity,
            hotelName: $context?->hotelName ?? $this->hotelName,
            hotelGrade: $context?->stars !== null ? (string) $context->stars : $this->hotelGrade,
            board: $this->board,
            airline: $this->airline,
            agency: $this->agency,
            hotelRecommendationContext: $context,
            rank: $this->rank,
        );
    }
}
