<?php

namespace App\Modules\Hotel\ValueObject;

use App\Modules\SearchSource\Entity\SearchSource;

final class HotelOfferCandidate
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $sourceIdentifier,
        public string $sourceName,
        public string $providerCode,
        public ?string $externalOfferId,
        public ?string $roomName,
        public ?string $boardType,
        public string $currency,
        public string $totalPrice,
        public ?string $bookingUrl,
        public ?string $availabilityStatus,
        public array $metadata = [],
    ) {
        $this->sourceIdentifier = self::normalizeCode($this->sourceIdentifier);
        $this->sourceName = trim($this->sourceName);
        $this->providerCode = self::normalizeCode($this->providerCode);
        $this->externalOfferId = self::nullableString($this->externalOfferId);
        $this->roomName = self::nullableString($this->roomName);
        $this->boardType = self::nullableString($this->boardType);
        $this->currency = strtoupper(trim($this->currency));
        $this->totalPrice = trim($this->totalPrice);
        $this->bookingUrl = self::nullableString($this->bookingUrl);
        $this->availabilityStatus = self::nullableString($this->availabilityStatus !== null ? strtolower($this->availabilityStatus) : null);
    }

    public static function sourceIdentifier(SearchSource $source): string
    {
        return HotelCandidate::sourceIdentifier($source);
    }

    private static function nullableString(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value !== '' ? $value : null;
    }

    private static function normalizeCode(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]+/', '_', $value) ?? '';

        return trim($value, '_-');
    }
}
