<?php

namespace App\Modules\Tour\Service;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Tour\Entity\ExternalTourOffer;
use App\Modules\Tour\Entity\TourPackage;
use App\Modules\Tour\Enum\TourOfferSourceType;
use App\Modules\Tour\Enum\TourPricingMode;
use App\Modules\Tour\Repository\ExternalTourOfferRepository;
use App\Modules\Tour\Repository\TourPackageRepository;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchRequest;
use App\Modules\Tour\ValueObject\TourMoney;
use App\Modules\Tour\ValueObject\TourPricingCandidate;

final readonly class TourOfferResolver
{
    public function __construct(
        private TourPackageRepository $packageRepository,
        private ExternalTourOfferRepository $externalOfferRepository,
    ) {
    }

    /**
     * @param int[] $childrenAges
     *
     * @return TourPricingCandidate[]
     */
    public function resolve(?Airport $origin, City $destination, ?\DateTimeImmutable $departureDate, ?\DateTimeImmutable $returnDate, ?\DateTimeImmutable $validFrom, ?\DateTimeImmutable $validTo, ?int $nights, int $adults, int $children, int $infants, array $childrenAges = [], int $rooms = 1, bool $publicOnly = true): array
    {
        $request = new ExternalTourOfferSearchRequest($origin, $destination, $departureDate, $returnDate, $validFrom, $validTo, $nights, $adults, $children, $infants, $childrenAges, rooms: $rooms);
        $candidates = [];

        foreach ($this->packageRepository->findActivePublicVisible($destination) as $package) {
            if (!$this->ownMatches($package, $request, $publicOnly)) {
                continue;
            }
            $price = $this->ownPrice($package, $request);
            if ($price !== null) {
                $candidates[] = $this->ownCandidate($package, $price);
            }
        }

        foreach ($this->externalOfferRepository->findFreshResolvableMatches($request, new \DateTimeImmutable()) as $offer) {
            if ($this->externalMatches($offer, $request)) {
                $candidates[] = $this->externalCandidate($offer);
            }
        }

        usort($candidates, [$this, 'compare']);

        return $candidates;
    }

    /**
     * @param int[] $childrenAges
     *
     * @return TourPricingCandidate[]
     */
    public function resolveForDestination(?Airport $origin, ?Country $destinationCountry, ?City $destinationCity, ?\DateTimeImmutable $departureDate, ?\DateTimeImmutable $returnDate, ?\DateTimeImmutable $validFrom, ?\DateTimeImmutable $validTo, ?int $nights, int $adults, int $children, int $infants, array $childrenAges = [], int $rooms = 1, bool $publicOnly = true): array
    {
        if ($destinationCity instanceof City) {
            return $this->resolve($origin, $destinationCity, $departureDate, $returnDate, $validFrom, $validTo, $nights, $adults, $children, $infants, $childrenAges, $rooms, $publicOnly);
        }

        if (!$destinationCountry instanceof Country) {
            return [];
        }

        $candidates = [];

        foreach ($this->packageRepository->findActivePublicVisibleByCountry($destinationCountry) as $package) {
            if (!$this->ownMatchesCountry($package, $origin, $destinationCountry, $departureDate, $returnDate, $validFrom, $validTo, $nights, $adults, $children, $infants, $childrenAges, $publicOnly)) {
                continue;
            }
            $price = $package->getPricingMode() === TourPricingMode::TOTAL_PARTY ? $package->getTotalPrice() : $package->calculatedPartyPrice();
            if ($price !== null) {
                $candidates[] = $this->ownCandidate($package, $price);
            }
        }

        foreach ($this->externalOfferRepository->findFreshResolvableCountryMatches($destinationCountry, $origin, $nights, $adults, $children, $infants, $childrenAges, new \DateTimeImmutable()) as $offer) {
            if ($this->externalMatchesCountry($offer, $validFrom, $validTo, $departureDate, $returnDate, $nights)) {
                $candidates[] = $this->externalCandidate($offer);
            }
        }

        usort($candidates, [$this, 'compare']);

        return $candidates;
    }

    private function ownMatches(TourPackage $package, ExternalTourOfferSearchRequest $request, bool $publicOnly): bool
    {
        if (!$package->isActive() || ($publicOnly && !$package->isPublicVisible())) {
            return false;
        }
        if ($package->getDestinationCity()?->getId() !== $request->destinationCity->getId()) {
            return false;
        }
        if ($package->getOriginAirport() instanceof Airport && $request->originAirport instanceof Airport && $package->getOriginAirport()->getId() !== $request->originAirport->getId()) {
            return false;
        }
        if ($package->getOriginAirport() instanceof Airport && !$request->originAirport instanceof Airport) {
            return false;
        }
        if ($request->nights !== null && $package->getNights() !== $request->nights) {
            return false;
        }
        if ($package->getAdults() !== $request->adults || $package->getChildren() !== $request->children || $package->getInfants() !== $request->infants || $package->getChildrenAges() !== $request->childrenAges) {
            return false;
        }

        return $this->datesMatch($package->getDepartureDate(), $package->getReturnDate(), $package->getValidFrom(), $package->getValidTo(), $request);
    }

    private function externalMatches(ExternalTourOffer $offer, ExternalTourOfferSearchRequest $request): bool
    {
        return $offer->getAdults() === $request->adults
            && $offer->getChildren() === $request->children
            && $offer->getInfants() === $request->infants
            && $offer->getChildrenAges() === $request->childrenAges
            && ($request->nights === null || $offer->getNights() === $request->nights)
            && $this->datesMatch($offer->getDepartureDate(), $offer->getReturnDate(), $offer->getValidFrom(), $offer->getValidTo(), $request);
    }

    /**
     * @param int[] $childrenAges
     */
    private function ownMatchesCountry(TourPackage $package, ?Airport $origin, Country $destinationCountry, ?\DateTimeImmutable $departureDate, ?\DateTimeImmutable $returnDate, ?\DateTimeImmutable $validFrom, ?\DateTimeImmutable $validTo, ?int $nights, int $adults, int $children, int $infants, array $childrenAges, bool $publicOnly): bool
    {
        if (!$package->isActive() || ($publicOnly && !$package->isPublicVisible())) {
            return false;
        }
        if ($package->getDestinationCity()?->getCountry()?->getId() !== $destinationCountry->getId()) {
            return false;
        }
        if ($package->getOriginAirport() instanceof Airport && $origin instanceof Airport && $package->getOriginAirport()->getId() !== $origin->getId()) {
            return false;
        }
        if ($package->getOriginAirport() instanceof Airport && !$origin instanceof Airport) {
            return false;
        }
        if ($nights !== null && $package->getNights() !== $nights) {
            return false;
        }
        if ($package->getAdults() !== $adults || $package->getChildren() !== $children || $package->getInfants() !== $infants || $package->getChildrenAges() !== $childrenAges) {
            return false;
        }

        return $this->datesMatchValues($package->getDepartureDate(), $package->getReturnDate(), $package->getValidFrom(), $package->getValidTo(), $departureDate, $returnDate, $validFrom, $validTo, $nights);
    }

    private function externalMatchesCountry(ExternalTourOffer $offer, ?\DateTimeImmutable $validFrom, ?\DateTimeImmutable $validTo, ?\DateTimeImmutable $departureDate, ?\DateTimeImmutable $returnDate, ?int $nights): bool
    {
        return $this->datesMatchValues($offer->getDepartureDate(), $offer->getReturnDate(), $offer->getValidFrom(), $offer->getValidTo(), $departureDate, $returnDate, $validFrom, $validTo, $nights);
    }

    private function datesMatch(?\DateTimeImmutable $departureDate, ?\DateTimeImmutable $returnDate, ?\DateTimeImmutable $validFrom, ?\DateTimeImmutable $validTo, ExternalTourOfferSearchRequest $request): bool
    {
        return $this->datesMatchValues($departureDate, $returnDate, $validFrom, $validTo, $request->departureDate, $request->returnDate, $request->validFrom, $request->validTo, $request->nights);
    }

    private function datesMatchValues(?\DateTimeImmutable $departureDate, ?\DateTimeImmutable $returnDate, ?\DateTimeImmutable $validFrom, ?\DateTimeImmutable $validTo, ?\DateTimeImmutable $requestedDepartureDate, ?\DateTimeImmutable $requestedReturnDate, ?\DateTimeImmutable $requestedValidFrom, ?\DateTimeImmutable $requestedValidTo, ?int $requestedNights): bool
    {
        if ($requestedDepartureDate instanceof \DateTimeImmutable) {
            if ($departureDate instanceof \DateTimeImmutable) {
                return $departureDate->format('Y-m-d') === $requestedDepartureDate->format('Y-m-d')
                    && (!$requestedReturnDate instanceof \DateTimeImmutable || $returnDate?->format('Y-m-d') === $requestedReturnDate->format('Y-m-d'));
            }

            return $validFrom instanceof \DateTimeImmutable
                && $requestedDepartureDate >= $validFrom
                && (!$validTo instanceof \DateTimeImmutable || $requestedDepartureDate <= $validTo);
        }

        if (!$requestedValidFrom instanceof \DateTimeImmutable || !$requestedValidTo instanceof \DateTimeImmutable) {
            return false;
        }

        if ($departureDate instanceof \DateTimeImmutable) {
            $candidateReturn = $returnDate;
            if (!$candidateReturn instanceof \DateTimeImmutable && $requestedNights !== null) {
                $candidateReturn = $departureDate->modify('+' . $requestedNights . ' days');
            }

            return $candidateReturn instanceof \DateTimeImmutable
                && $departureDate >= $requestedValidFrom
                && $candidateReturn <= $requestedValidTo;
        }

        return $validFrom instanceof \DateTimeImmutable
            && $validTo instanceof \DateTimeImmutable
            && $validFrom >= $requestedValidFrom
            && $validTo <= $requestedValidTo;
    }

    private function ownPrice(TourPackage $package, ExternalTourOfferSearchRequest $request): ?string
    {
        if ($package->getPricingMode() === TourPricingMode::TOTAL_PARTY) {
            return $package->getTotalPrice();
        }

        return $package->calculatedPartyPrice();
    }

    private function ownCandidate(TourPackage $package, string $price): TourPricingCandidate
    {
        return new TourPricingCandidate(
            sourceType: TourOfferSourceType::OWN,
            priority: $package->getPriority(),
            title: $package->getName(),
            destination: (string) $package->getDestinationCity()?->getName(),
            dates: $package->getDateLabel(),
            nights: $package->getNights(),
            hotelSummary: $package->getHotel()?->getName(),
            flightSummary: $package->getFlightOffer()?->getRouteLabel(),
            inclusions: $package->getInclusions(),
            currency: $package->getCurrency(),
            totalPrice: $price,
            bookingUrl: null,
            fetchedAt: null,
            expiresAt: null,
            tourPackageId: (int) $package->getId(),
            tourPackage: $package,
        );
    }

    private function externalCandidate(ExternalTourOffer $offer): TourPricingCandidate
    {
        return new TourPricingCandidate(
            sourceType: TourOfferSourceType::EXTERNAL,
            priority: 0,
            title: $offer->getTitle(),
            destination: $offer->getDestinationCity()?->getName() ?? $offer->getDestinationText(),
            dates: $offer->getDateLabel(),
            nights: $offer->getNights(),
            hotelSummary: $offer->getHotelLabel(),
            flightSummary: $offer->getFlightSummary(),
            inclusions: $offer->getInclusions(),
            currency: $offer->getCurrency(),
            totalPrice: $offer->getTotalPrice(),
            bookingUrl: $offer->getBookingUrl(),
            fetchedAt: $offer->getFetchedAt(),
            expiresAt: $offer->getExpiresAt(),
            externalTourOfferId: (int) $offer->getId(),
            externalTourOffer: $offer,
        );
    }

    private function compare(TourPricingCandidate $left, TourPricingCandidate $right): int
    {
        if ($left->sourceType !== $right->sourceType) {
            return $left->sourceType === TourOfferSourceType::OWN ? -1 : 1;
        }
        if ($left->priority !== $right->priority) {
            return $right->priority <=> $left->priority;
        }
        if ($left->currency === $right->currency) {
            return TourMoney::cents($left->totalPrice) <=> TourMoney::cents($right->totalPrice);
        }

        return $left->currency <=> $right->currency;
    }
}
