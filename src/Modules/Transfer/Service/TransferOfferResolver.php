<?php

namespace App\Modules\Transfer\Service;

use App\Modules\Transfer\Enum\TransferOfferSourceType;
use App\Modules\Transfer\Repository\TransferOfferRepository;
use App\Modules\Transfer\Repository\TransferProductRepository;
use App\Modules\Transfer\ValueObject\TransferEndpointContext;
use App\Modules\Transfer\ValueObject\TransferMoney;
use App\Modules\Transfer\ValueObject\TransferPricingCandidate;

/**
 * Answers "what transfers are available for this airport/hotel/date/
 * passenger count?" for future Trip Planner consumption. Only our own
 * TransferOffer records are resolved today; the comparator already ranks
 * OWN ahead of EXTERNAL so a future external candidate source can be merged
 * in without changing ranking behavior.
 */
final readonly class TransferOfferResolver
{
    public function __construct(
        private TransferProductRepository $transferProductRepository,
        private TransferOfferRepository $transferOfferRepository,
    ) {
    }

    /**
     * @return TransferPricingCandidate[]
     */
    public function resolve(TransferEndpointContext $from, TransferEndpointContext $to, \DateTimeImmutable $date, int $passengers): array
    {
        $products = $this->transferProductRepository->findMatchingOwnProducts($from, $to);
        if ($products === []) {
            return [];
        }

        $candidates = [];
        foreach ($this->transferOfferRepository->findActiveForProducts($products) as $offer) {
            if (!$offer->matches($date, $passengers)) {
                continue;
            }

            $totalPrice = $offer->getTotalPrice();
            if ($totalPrice === null) {
                continue;
            }

            $candidates[] = new TransferPricingCandidate(
                TransferOfferSourceType::OWN,
                $offer->getPriority(),
                $offer->getCurrency(),
                $totalPrice,
                $offer->getTransferProduct(),
                $offer,
            );
        }

        usort($candidates, $this->compare(...));

        return $candidates;
    }

    private function compare(TransferPricingCandidate $left, TransferPricingCandidate $right): int
    {
        if ($left->sourceType !== $right->sourceType) {
            return $left->sourceType === TransferOfferSourceType::OWN ? -1 : 1;
        }

        if ($left->priority !== $right->priority) {
            return $right->priority <=> $left->priority;
        }

        if ($left->currency === $right->currency) {
            return TransferMoney::cents($left->totalPrice) <=> TransferMoney::cents($right->totalPrice);
        }

        return $left->currency <=> $right->currency;
    }
}
