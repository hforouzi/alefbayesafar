<?php

namespace App\Modules\Hotel\ValueObject;

use App\Modules\Hotel\Entity\HotelOffer;
use App\Modules\Hotel\Entity\HotelRate;
use App\Modules\Hotel\Enum\HotelPriceSourceType;

final readonly class HotelPricingCandidate
{
    public function __construct(
        public HotelPriceSourceType $sourceType,
        public int $priority,
        public string $currency,
        public string $totalPrice,
        public ?string $pricePerNight,
        public ?string $roomName,
        public ?string $boardType,
        public ?HotelRate $rate = null,
        public ?HotelOffer $externalOffer = null,
    ) {
    }
}
