<?php

namespace App\Modules\Activity\Service;

use App\Modules\Activity\Enum\ActivityCategory;
use App\Modules\Activity\Enum\ActivityOfferSourceType;
use App\Modules\Activity\Repository\ActivityOfferRepository;
use App\Modules\Activity\ValueObject\ActivityMoney;
use App\Modules\Activity\ValueObject\ActivityPricingCandidate;
use App\Modules\Destination\Entity\City;

/**
 * Answers "what activities are available for this destination/date/party?"
 * for future Trip Planner consumption. Only our own ActivityOffer records
 * are resolved today; the comparator already ranks OWN ahead of EXTERNAL so
 * a future external candidate source can be merged in without changing
 * ranking behavior.
 */
final readonly class ActivityOfferResolver
{
    public function __construct(
        private ActivityOfferRepository $activityOfferRepository,
    ) {
    }

    /**
     * @return ActivityPricingCandidate[]
     */
    public function resolve(
        City $destinationCity,
        \DateTimeImmutable $date,
        int $adults,
        int $children = 0,
        int $infants = 0,
        ?ActivityCategory $category = null,
    ): array {
        $candidates = [];

        foreach ($this->activityOfferRepository->findCandidatesForSearch($destinationCity, $category) as $offer) {
            if (!$offer->matches($date, $adults, $children, $infants)) {
                continue;
            }

            $totalPrice = $offer->calculatePartyPrice($adults, $children, $infants);
            if ($totalPrice === null) {
                continue;
            }

            $candidates[] = new ActivityPricingCandidate(
                ActivityOfferSourceType::OWN,
                $offer->getPriority(),
                $offer->getCurrency(),
                $totalPrice,
                $offer->getActivity(),
                $offer,
            );
        }

        usort($candidates, $this->compare(...));

        return $candidates;
    }

    private function compare(ActivityPricingCandidate $left, ActivityPricingCandidate $right): int
    {
        if ($left->sourceType !== $right->sourceType) {
            return $left->sourceType === ActivityOfferSourceType::OWN ? -1 : 1;
        }

        if ($left->priority !== $right->priority) {
            return $right->priority <=> $left->priority;
        }

        if ($left->currency === $right->currency) {
            return ActivityMoney::cents($left->totalPrice) <=> ActivityMoney::cents($right->totalPrice);
        }

        return $left->currency <=> $right->currency;
    }
}
