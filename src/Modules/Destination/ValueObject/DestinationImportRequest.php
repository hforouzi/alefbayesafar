<?php

namespace App\Modules\Destination\ValueObject;

final readonly class DestinationImportRequest
{
    /**
     * @param string[] $providerCodes
     */
    public function __construct(
        public string $targetType,
        public string $countryName,
        public ?string $cityName,
        public array $providerCodes,
        public bool $refresh = false,
    ) {
    }
}
