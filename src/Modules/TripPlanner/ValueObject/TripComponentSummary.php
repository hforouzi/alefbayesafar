<?php

namespace App\Modules\TripPlanner\ValueObject;

final readonly class TripComponentSummary
{
    public function __construct(
        public string $type,
        public string $title,
        public ?string $sourceType = null,
        public ?string $sourceName = null,
        public ?string $currency = null,
        public ?string $price = null,
        public ?string $details = null,
        public ?string $url = null,
    ) {
    }
}
