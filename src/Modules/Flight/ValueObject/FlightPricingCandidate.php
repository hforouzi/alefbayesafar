<?php

namespace App\Modules\Flight\ValueObject;

use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\Flight\Enum\FlightPriceSourceType;
use App\Modules\Flight\Enum\FlightTripType;

final readonly class FlightPricingCandidate
{
    public function __construct(
        public FlightPriceSourceType $sourceType,
        public int $priority,
        public int $flightOfferId,
        public FlightTripType $tripType,
        public string $routeSummary,
        public string $airlineSummary,
        public string $departureSummary,
        public FlightCabinClass $cabinClass,
        public string $currency,
        public string $totalPrice,
        public ?string $baggage,
        public ?\DateTimeImmutable $fetchedAt = null,
        public ?\DateTimeImmutable $expiresAt = null,
        public ?FlightOffer $flightOffer = null,
    ) {
    }
}
