<?php

namespace App\Modules\Hotel\Service;

use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelOffer;
use App\Modules\Hotel\Entity\HotelRate;
use App\Modules\Hotel\Enum\HotelPriceSourceType;
use App\Modules\Hotel\Repository\HotelOfferRepository;
use App\Modules\Hotel\Repository\HotelRateRepository;
use App\Modules\Hotel\ValueObject\HotelOfferSearchRequest;
use App\Modules\Hotel\ValueObject\HotelPricingCandidate;

final readonly class HotelPricingResolver
{
    private const EXTERNAL_PRIORITY = 50;

    public function __construct(
        private HotelRateRepository $rateRepository,
        private HotelOfferRepository $offerRepository,
    ) {
    }

    /**
     * @param int[] $childrenAges
     *
     * @return HotelPricingCandidate[]
     */
    public function resolve(Hotel $hotel, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut, int $adults, int $children = 0, array $childrenAges = []): array
    {
        $request = new HotelOfferSearchRequest($checkIn, $checkOut, $adults, $children, $childrenAges);
        $nights = $this->nights($request->checkIn, $request->checkOut);
        $candidates = [];

        foreach ($this->rateRepository->findMatchingOwnRates($hotel, $request->checkIn, $request->checkOut, $request->adults, $request->children, $request->childrenAges) as $rate) {
            $candidates[] = $this->ownCandidate($rate, $nights);
        }

        foreach ($this->offerRepository->findForSearchContext($hotel, $request) as $offer) {
            if ($offer->getAvailabilityStatus() === HotelOffer::AVAILABILITY_UNAVAILABLE) {
                continue;
            }

            $candidates[] = new HotelPricingCandidate(
                sourceType: HotelPriceSourceType::EXTERNAL,
                priority: self::EXTERNAL_PRIORITY,
                currency: $offer->getCurrency(),
                totalPrice: $offer->getTotalPrice(),
                pricePerNight: null,
                roomName: $offer->getRoomName(),
                boardType: $offer->getBoardType(),
                externalOffer: $offer,
            );
        }

        usort($candidates, [$this, 'compareCandidates']);

        return $candidates;
    }

    private function ownCandidate(HotelRate $rate, int $nights): HotelPricingCandidate
    {
        return new HotelPricingCandidate(
            sourceType: HotelPriceSourceType::OWN,
            priority: $rate->getPriority(),
            currency: $rate->getCurrency(),
            totalPrice: $this->multiplyDecimal($rate->getPricePerNight(), $nights),
            pricePerNight: $rate->getPricePerNight(),
            roomName: $rate->getRoomType()?->getName(),
            boardType: $rate->getBoardType(),
            rate: $rate,
        );
    }

    private function compareCandidates(HotelPricingCandidate $left, HotelPricingCandidate $right): int
    {
        if ($left->sourceType !== $right->sourceType) {
            return $left->sourceType === HotelPriceSourceType::OWN ? -1 : 1;
        }

        if ($left->priority !== $right->priority) {
            return $right->priority <=> $left->priority;
        }

        if ($left->currency === $right->currency) {
            return $this->decimalCents($left->totalPrice) <=> $this->decimalCents($right->totalPrice);
        }

        return $left->currency <=> $right->currency;
    }

    private function nights(\DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): int
    {
        $nights = (int) $checkIn->diff($checkOut)->format('%a');
        if ($nights < 1) {
            throw new \InvalidArgumentException('Check-out must be after check-in.');
        }

        return $nights;
    }

    private function multiplyDecimal(string $amount, int $multiplier): string
    {
        $cents = $this->decimalCents($amount) * $multiplier;
        $major = intdiv($cents, 100);
        $minor = $cents % 100;

        return sprintf('%d.%02d', $major, $minor);
    }

    private function decimalCents(string $amount): int
    {
        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches) !== 1) {
            throw new \InvalidArgumentException('Money values must be decimal strings.');
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '0', 2, '0');
    }
}
