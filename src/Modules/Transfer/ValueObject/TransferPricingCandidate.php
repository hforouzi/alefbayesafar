<?php

namespace App\Modules\Transfer\ValueObject;

use App\Modules\Transfer\Entity\TransferOffer;
use App\Modules\Transfer\Entity\TransferProduct;
use App\Modules\Transfer\Enum\TransferOfferSourceType;

final readonly class TransferPricingCandidate
{
    public function __construct(
        public TransferOfferSourceType $sourceType,
        public int $priority,
        public string $currency,
        public string $totalPrice,
        public TransferProduct $transferProduct,
        public ?TransferOffer $transferOffer = null,
    ) {
    }
}
