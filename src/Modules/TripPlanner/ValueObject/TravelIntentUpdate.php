<?php

namespace App\Modules\TripPlanner\ValueObject;

/**
 * Structured, partial update extracted from one free-text user message.
 *
 * Every field is optional: null/false means "the message said nothing about
 * this," not "clear the existing value." Names are raw text and must be
 * resolved against the catalog (City/Country repositories) by the caller —
 * this value object never touches the database.
 */
final readonly class TravelIntentUpdate
{
    /**
     * @param int[]|null $childrenAges
     */
    public function __construct(
        public ?string $originCityName = null,
        public ?string $destinationCityName = null,
        public ?string $destinationCountryName = null,
        public bool $destinationOpen = false,
        public ?int $nights = null,
        public ?int $adults = null,
        public ?int $children = null,
        public ?array $childrenAges = null,
        public ?string $dateMode = null,
        public ?string $departureDate = null,
        public ?string $returnDate = null,
        public ?string $windowStart = null,
        public ?string $windowEnd = null,
        public ?string $monthHint = null,
        public bool $flexibleHint = false,
        public ?string $budget = null,
        public ?int $hotelStarPreference = null,
        public ?bool $breakfastPreferred = null,
        public ?bool $directFlightPreferred = null,
        public bool $cheaperRequested = false,
        public bool $betterHotelRequested = false,
        public bool $widenWindowRequested = false,
        public bool $anotherCityRequested = false,
        public bool $recognized = false,
    ) {
    }
}
