<?php

namespace App\Modules\Flight\ValueObject;

use App\Modules\Flight\Enum\FlightAvailabilityStatus;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\Flight\Enum\FlightDirection;
use App\Modules\Flight\Enum\FlightTripType;
use App\Modules\SearchSource\Entity\SearchSource;

final readonly class FlightOfferCandidate
{
    /**
     * @param FlightOfferLegCandidate[] $outboundLegs
     * @param FlightOfferLegCandidate[] $inboundLegs
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $sourceIdentifier,
        public string $sourceName,
        public string $providerCode,
        public ?string $externalOfferId,
        public FlightTripType $tripType,
        public int $adults,
        public int $children,
        public int $infants,
        public FlightCabinClass $cabinClass,
        public string $currency,
        public string $totalPrice,
        public ?string $baggage,
        public ?string $bookingUrl,
        public FlightAvailabilityStatus $availabilityStatus,
        public array $outboundLegs,
        public array $inboundLegs = [],
        public array $metadata = [],
    ) {
        if ($sourceIdentifier === '' || $sourceName === '' || $providerCode === '') {
            throw new \InvalidArgumentException('Flight offer candidate requires source and provider identity.');
        }

        if ($adults < 1 || $children < 0 || $infants < 0 || $infants > $adults) {
            throw new \InvalidArgumentException('Flight offer candidate passenger counts are invalid.');
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new \InvalidArgumentException('Flight offer candidate currency must be a three-letter uppercase code.');
        }

        if (FlightMoney::normalize($totalPrice) !== $totalPrice) {
            throw new \InvalidArgumentException('Flight offer candidate total price must be a positive decimal string.');
        }

        if ($outboundLegs === []) {
            throw new \InvalidArgumentException('Flight offer candidate requires at least one outbound leg.');
        }

        foreach ($outboundLegs as $leg) {
            if ($leg->direction !== FlightDirection::OUTBOUND) {
                throw new \InvalidArgumentException('Flight offer candidate outbound legs must use OUTBOUND direction.');
            }
        }

        foreach ($inboundLegs as $leg) {
            if ($leg->direction !== FlightDirection::INBOUND) {
                throw new \InvalidArgumentException('Flight offer candidate inbound legs must use INBOUND direction.');
            }
        }

        if ($tripType === FlightTripType::ONE_WAY && $inboundLegs !== []) {
            throw new \InvalidArgumentException('One-way flight offer candidate cannot contain inbound legs.');
        }

        if ($tripType === FlightTripType::ROUND_TRIP && $inboundLegs === []) {
            throw new \InvalidArgumentException('Round-trip flight offer candidate requires inbound legs.');
        }
    }

    public static function sourceIdentifier(SearchSource $source): string
    {
        return $source->getProvider() . ':' . $source->getDomain() . ':' . ($source->getLanguage() ?? '');
    }

    public function firstOutbound(): FlightOfferLegCandidate
    {
        return $this->outboundLegs[0];
    }

    public function lastOutbound(): FlightOfferLegCandidate
    {
        return $this->outboundLegs[array_key_last($this->outboundLegs)];
    }
}
