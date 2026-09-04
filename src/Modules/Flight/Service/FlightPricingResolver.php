<?php

namespace App\Modules\Flight\Service;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Entity\FlightOfferLeg;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\Flight\Enum\FlightDirection;
use App\Modules\Flight\Enum\FlightPriceSourceType;
use App\Modules\Flight\Enum\FlightPricingMode;
use App\Modules\Flight\Enum\FlightTripType;
use App\Modules\Flight\Repository\FlightOfferRepository;
use App\Modules\Flight\ValueObject\FlightMoney;
use App\Modules\Flight\ValueObject\FlightOfferSearchRequest;
use App\Modules\Flight\ValueObject\FlightPricingCandidate;

final readonly class FlightPricingResolver
{
    public function __construct(private FlightOfferRepository $offerRepository)
    {
    }

    /**
     * @return FlightPricingCandidate[]
     */
    public function resolve(Airport $origin, Airport $destination, \DateTimeImmutable $departureDate, ?\DateTimeImmutable $returnDate, int $adults, int $children, int $infants, FlightCabinClass $cabinClass, ?bool $directOnly = null): array
    {
        $request = new FlightOfferSearchRequest($origin, $destination, $departureDate, $returnDate, $adults, $children, $infants, $cabinClass, $directOnly);
        $now = new \DateTimeImmutable();
        $candidates = [];

        foreach ($this->offerRepository->findPotentialOwnMatches($request) as $offer) {
            if (!$this->ownOfferMatches($offer, $request)) {
                continue;
            }

            $totalPrice = $this->ownTotalPrice($offer, $request);
            if ($totalPrice === null) {
                continue;
            }

            $candidates[] = $this->candidate($offer, $totalPrice);
        }

        foreach ($this->offerRepository->findPotentialExternalMatches($request, $now) as $offer) {
            if (!$this->externalOfferMatches($offer, $request) || $offer->getTotalPrice() === null) {
                continue;
            }

            $candidates[] = $this->candidate($offer, $offer->getTotalPrice());
        }

        usort($candidates, [$this, 'compareCandidates']);

        return $candidates;
    }

    private function ownOfferMatches(FlightOffer $offer, FlightOfferSearchRequest $request): bool
    {
        if ($offer->getValidFrom() instanceof \DateTimeImmutable && $request->departureDate < $offer->getValidFrom()) {
            return false;
        }

        if ($offer->getValidTo() instanceof \DateTimeImmutable && $request->departureDate > $offer->getValidTo()) {
            return false;
        }

        if ($request->directOnly === true && (\count($offer->getOrderedLegs(FlightDirection::OUTBOUND)) > 1 || \count($offer->getOrderedLegs(FlightDirection::INBOUND)) > 1)) {
            return false;
        }

        return $this->routeAndDatesMatch($offer, $request);
    }

    private function externalOfferMatches(FlightOffer $offer, FlightOfferSearchRequest $request): bool
    {
        return ($offer->getMetadata()['searchContextHash'] ?? null) === $request->contextHash()
            && $this->routeAndDatesMatch($offer, $request);
    }

    private function routeAndDatesMatch(FlightOffer $offer, FlightOfferSearchRequest $request): bool
    {
        $outbound = $offer->getOrderedLegs(FlightDirection::OUTBOUND);
        if ($outbound === []) {
            return false;
        }

        $firstOutbound = $outbound[0];
        $lastOutbound = $outbound[array_key_last($outbound)];
        if ($firstOutbound->getOriginAirport()?->getId() !== $request->originAirport->getId() || $lastOutbound->getDestinationAirport()?->getId() !== $request->destinationAirport->getId()) {
            return false;
        }

        if ($firstOutbound->getDepartureAt()?->format('Y-m-d') !== $request->departureDate->format('Y-m-d')) {
            return false;
        }

        if ($request->tripType === FlightTripType::ROUND_TRIP) {
            $inbound = $offer->getOrderedLegs(FlightDirection::INBOUND);
            if ($inbound === []) {
                return false;
            }

            $firstInbound = $inbound[0];
            $lastInbound = $inbound[array_key_last($inbound)];
            if ($firstInbound->getOriginAirport()?->getId() !== $request->destinationAirport->getId() || $lastInbound->getDestinationAirport()?->getId() !== $request->originAirport->getId()) {
                return false;
            }

            if (!$request->returnDate instanceof \DateTimeImmutable || $firstInbound->getDepartureAt()?->format('Y-m-d') !== $request->returnDate->format('Y-m-d')) {
                return false;
            }
        }

        return true;
    }

    private function ownTotalPrice(FlightOffer $offer, FlightOfferSearchRequest $request): ?string
    {
        if ($offer->getPricingMode() === FlightPricingMode::TOTAL_PARTY) {
            return $offer->getAdults() === $request->adults
                && $offer->getChildren() === $request->children
                && $offer->getInfants() === $request->infants
                ? $offer->getTotalPrice()
                : null;
        }

        $total = 0;
        if ($request->adults > 0) {
            $adultPrice = $offer->getAdultPrice();
            if ($adultPrice === null) {
                return null;
            }
            $total += FlightMoney::cents($adultPrice) * $request->adults;
        }
        if ($request->children > 0) {
            $childPrice = $offer->getChildPrice();
            if ($childPrice === null) {
                return null;
            }
            $total += FlightMoney::cents($childPrice) * $request->children;
        }
        if ($request->infants > 0) {
            $infantPrice = $offer->getInfantPrice();
            if ($infantPrice === null) {
                return null;
            }
            $total += FlightMoney::cents($infantPrice) * $request->infants;
        }

        return FlightMoney::fromCents($total);
    }

    private function candidate(FlightOffer $offer, string $totalPrice): FlightPricingCandidate
    {
        return new FlightPricingCandidate(
            sourceType: $offer->getSourceType(),
            priority: $offer->getPriority(),
            flightOfferId: (int) $offer->getId(),
            tripType: $offer->getTripType(),
            routeSummary: $offer->getRouteLabel(),
            airlineSummary: $offer->getPrimaryAirlineLabel(),
            departureSummary: $this->departureSummary($offer),
            cabinClass: $offer->getCabinClass(),
            currency: $offer->getCurrency(),
            totalPrice: $totalPrice,
            baggage: $offer->getBaggage(),
            fetchedAt: $offer->getFetchedAt(),
            expiresAt: $offer->getExpiresAt(),
            flightOffer: $offer,
        );
    }

    private function departureSummary(FlightOffer $offer): string
    {
        $outbound = $offer->getOrderedLegs(FlightDirection::OUTBOUND);
        $first = $outbound[0] ?? null;
        if (!$first instanceof FlightOfferLeg || !$first->getDepartureAt() instanceof \DateTimeImmutable) {
            return '-';
        }

        return $first->getDepartureAt()->format('Y-m-d H:i');
    }

    private function compareCandidates(FlightPricingCandidate $left, FlightPricingCandidate $right): int
    {
        if ($left->sourceType !== $right->sourceType) {
            return $left->sourceType === FlightPriceSourceType::OWN ? -1 : 1;
        }

        if ($left->priority !== $right->priority) {
            return $right->priority <=> $left->priority;
        }

        if ($left->currency === $right->currency) {
            return FlightMoney::cents($left->totalPrice) <=> FlightMoney::cents($right->totalPrice);
        }

        return $left->currency <=> $right->currency;
    }
}
