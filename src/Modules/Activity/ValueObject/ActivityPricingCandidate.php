<?php

namespace App\Modules\Activity\ValueObject;

use App\Modules\Activity\Entity\Activity;
use App\Modules\Activity\Entity\ActivityOffer;
use App\Modules\Activity\Enum\ActivityOfferSourceType;

final readonly class ActivityPricingCandidate
{
    public function __construct(
        public ActivityOfferSourceType $sourceType,
        public int $priority,
        public string $currency,
        public string $totalPrice,
        public Activity $activity,
        public ?ActivityOffer $activityOffer = null,
        public ?ExternalActivityOfferCandidate $externalCandidate = null,
    ) {
    }
}
