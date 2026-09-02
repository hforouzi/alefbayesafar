<?php

namespace App\Modules\Tour\ValueObject;

use App\Modules\Tour\Entity\ExternalTourOffer;
use App\Modules\Tour\Entity\TourPackage;
use App\Modules\Tour\Enum\TourOfferSourceType;

final readonly class TourPricingCandidate
{
    /**
     * @param string[] $inclusions
     */
    public function __construct(
        public TourOfferSourceType $sourceType,
        public int $priority,
        public string $title,
        public string $destination,
        public string $dates,
        public ?int $nights,
        public ?string $hotelSummary,
        public ?string $flightSummary,
        public array $inclusions,
        public string $currency,
        public string $totalPrice,
        public ?string $bookingUrl,
        public ?\DateTimeImmutable $fetchedAt,
        public ?\DateTimeImmutable $expiresAt,
        public ?int $tourPackageId = null,
        public ?int $externalTourOfferId = null,
        public ?TourPackage $tourPackage = null,
        public ?ExternalTourOffer $externalTourOffer = null,
    ) {
    }
}
