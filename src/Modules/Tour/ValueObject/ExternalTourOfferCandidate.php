<?php

namespace App\Modules\Tour\ValueObject;

use App\Modules\Tour\Enum\TourAvailabilityStatus;

final readonly class ExternalTourOfferCandidate
{
    /**
     * @param int[] $childrenAges
     * @param string[] $inclusions
     * @param string[] $exclusions
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $sourceIdentifier,
        public string $sourceName,
        public string $providerCode,
        public ?string $externalOfferId,
        public string $title,
        public ?string $originText,
        public string $destinationText,
        public ?\DateTimeImmutable $departureDate,
        public ?\DateTimeImmutable $returnDate,
        public ?\DateTimeImmutable $validFrom,
        public ?\DateTimeImmutable $validTo,
        public ?int $nights,
        public ?int $days,
        public ?string $hotelName,
        public ?string $roomName,
        public ?string $boardType,
        public ?string $flightSummary,
        public int $adults,
        public int $children,
        public int $infants,
        public array $childrenAges,
        public array $inclusions,
        public array $exclusions,
        public string $currency,
        public string $totalPrice,
        public ?string $bookingUrl,
        public TourAvailabilityStatus $availabilityStatus,
        public array $metadata = [],
    ) {
        if (trim($title) === '') {
            throw new \InvalidArgumentException('External tour candidate title is required.');
        }
        if (trim($destinationText) === '') {
            throw new \InvalidArgumentException('External tour candidate destination is required.');
        }
        if (!TourMoney::isPositiveDecimal($totalPrice)) {
            throw new \InvalidArgumentException('External tour candidate price must be positive.');
        }
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new \InvalidArgumentException('External tour candidate currency is invalid.');
        }
    }

    public static function sourceIdentifier(\App\Modules\SearchSource\Entity\SearchSource $source): string
    {
        return sprintf('%s:%s', $source->getProvider(), $source->getDomain());
    }
}
