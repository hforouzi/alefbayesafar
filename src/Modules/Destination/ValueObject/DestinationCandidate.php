<?php

namespace App\Modules\Destination\ValueObject;

final readonly class DestinationCandidate
{
    /**
     * @param array<string, mixed> $rawData
     */
    public function __construct(
        public string $provider,
        public string $type,
        public string $name,
        public ?string $countryName = null,
        public ?string $stateName = null,
        public ?string $cityName = null,
        public ?string $nameFa = null,
        public ?string $externalId = null,
        public ?string $sourceUrl = null,
        public ?string $sourceTitle = null,
        public ?string $iso2 = null,
        public ?string $iso3 = null,
        public ?string $admin1Code = null,
        public ?string $iataCode = null,
        public ?string $icaoCode = null,
        public null|float|string $latitude = null,
        public null|float|string $longitude = null,
        public ?\DateTimeImmutable $dataUpdatedAt = null,
        public array $rawData = [],
    ) {
    }
}
