<?php

namespace App\Modules\TripPlanner\ValueObject;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\TripPlanner\Enum\TripDateMode;
use App\Modules\TripPlanner\Enum\TripPlanningGoal;

final readonly class TripSearchRequest
{
    /**
     * @param int[] $childAges
     * @param string[] $activityCategories
     * @param array<int, array<string, mixed>> $candidateDateWindows
     */
    public function __construct(
        public ?Airport $originAirport,
        public ?City $originCity,
        public ?City $destinationCity,
        public ?\DateTimeImmutable $departureDate,
        public ?\DateTimeImmutable $returnDate,
        public ?int $nights,
        public int $adults,
        public int $children = 0,
        public int $infants = 0,
        public array $childAges = [],
        public ?string $budget = null,
        public string $preferredCurrency = 'EUR',
        public ?int $hotelStarPreference = null,
        public ?bool $breakfastPreferred = null,
        public ?FlightCabinClass $flightCabin = null,
        public bool $directFlightPreferred = false,
        public array $activityCategories = [],
        public bool $transferRequired = false,
        public TripDateMode $dateMode = TripDateMode::EXACT,
        public ?\DateTimeImmutable $windowStart = null,
        public ?\DateTimeImmutable $windowEnd = null,
        public TripPlanningGoal $goal = TripPlanningGoal::SPECIFIC_DESTINATION,
        public array $candidateDateWindows = [],
        public int $rooms = 1,
        public ?Country $destinationCountry = null,
        /**
         * Set only when a direct search from the user's own origin produced
         * no usable commercial result and CommercialDepartureResolver found a
         * validated alternative departure airport. userOriginCity/originCity
         * above always stays the user's real origin — this is never used to
         * silently replace it, only to let TripPlanner also price flights
         * from a nearby commercial gateway.
         */
        public ?Airport $commercialDepartureAirport = null,
    ) {
        if ($this->destinationCity instanceof City && $this->destinationCountry instanceof Country) {
            $cityCountry = $this->destinationCity->getCountry();
            if ($cityCountry instanceof Country && $cityCountry->getId() !== $this->destinationCountry->getId()) {
                throw new \InvalidArgumentException('Destination city must belong to the selected destination country.');
            }
        }
        if ($this->adults < 1) {
            throw new \InvalidArgumentException('At least one adult is required.');
        }
        if ($this->children < 0 || $this->infants < 0) {
            throw new \InvalidArgumentException('Children and infants cannot be negative.');
        }
        if (\count($this->childAges) !== $this->children) {
            throw new \InvalidArgumentException('Child ages must match the number of children.');
        }
        if ($this->nights !== null && $this->nights < 1) {
            throw new \InvalidArgumentException('Nights must be at least 1.');
        }
        if ($this->dateMode === TripDateMode::EXACT) {
            if (!$this->departureDate instanceof \DateTimeImmutable || !$this->returnDate instanceof \DateTimeImmutable) {
                throw new \InvalidArgumentException('Exact date searches require departure and return dates.');
            }
            if ($this->returnDate < $this->departureDate) {
                throw new \InvalidArgumentException('Return date must be after departure date.');
            }
        }
        if ($this->dateMode === TripDateMode::FLEXIBLE) {
            if (!$this->windowStart instanceof \DateTimeImmutable || !$this->windowEnd instanceof \DateTimeImmutable) {
                throw new \InvalidArgumentException('Flexible date searches require a window start and window end.');
            }
            if ($this->windowEnd < $this->windowStart) {
                throw new \InvalidArgumentException('Window end must be after window start.');
            }
            if ($this->nights === null) {
                throw new \InvalidArgumentException('Flexible date searches require nights.');
            }
        }
        if (preg_match('/^[A-Z]{3}$/', $this->preferredCurrency) !== 1) {
            throw new \InvalidArgumentException('Preferred currency must be a 3-letter uppercase code.');
        }
        if ($this->budget !== null) {
            TripMoney::normalize($this->budget);
        }
        if ($this->hotelStarPreference !== null && ($this->hotelStarPreference < 1 || $this->hotelStarPreference > 5)) {
            throw new \InvalidArgumentException('Hotel star preference must be between 1 and 5.');
        }
        if ($this->rooms < 1) {
            throw new \InvalidArgumentException('Rooms must be at least 1.');
        }
        foreach ($this->candidateDateWindows as $window) {
            $candidateDeparture = $window['departureDate'] ?? null;
            $candidateReturn = $window['returnDate'] ?? null;
            if (!$candidateDeparture instanceof \DateTimeImmutable || !$candidateReturn instanceof \DateTimeImmutable || $candidateReturn < $candidateDeparture) {
                throw new \InvalidArgumentException('Candidate date windows must contain valid departure and return dates.');
            }
        }
    }

    public function checkOutDate(): \DateTimeImmutable
    {
        if ($this->returnDate instanceof \DateTimeImmutable) {
            return $this->returnDate;
        }

        return $this->travelStartDate()->modify('+' . max(1, $this->nights ?? 1) . ' days');
    }

    public function nightsOrDerived(): int
    {
        if ($this->nights !== null) {
            return $this->nights;
        }

        return max(1, (int) $this->travelStartDate()->diff($this->checkOutDate())->format('%a'));
    }

    public function travelStartDate(): \DateTimeImmutable
    {
        if ($this->departureDate instanceof \DateTimeImmutable) {
            return $this->departureDate;
        }
        if ($this->windowStart instanceof \DateTimeImmutable) {
            return $this->windowStart;
        }

        throw new \LogicException('Trip search request has no usable travel start date.');
    }

    public function travelEndDate(): \DateTimeImmutable
    {
        if ($this->returnDate instanceof \DateTimeImmutable) {
            return $this->returnDate;
        }
        if ($this->windowEnd instanceof \DateTimeImmutable) {
            return $this->windowEnd;
        }

        return $this->checkOutDate();
    }

    public function isFlexible(): bool
    {
        return $this->dateMode === TripDateMode::FLEXIBLE;
    }

    public function requiresSpecificDestination(): bool
    {
        return $this->goal === TripPlanningGoal::SPECIFIC_DESTINATION;
    }

    public function hasDestinationScope(): bool
    {
        return $this->destinationCity instanceof City || $this->destinationCountry instanceof Country;
    }

    public function hasOriginScope(): bool
    {
        return $this->originCity instanceof City || $this->originAirport instanceof Airport;
    }

    public function destinationCountry(): ?Country
    {
        if ($this->destinationCountry instanceof Country) {
            return $this->destinationCountry;
        }

        return $this->destinationCity?->getCountry();
    }

    /**
     * @param array<int, array{departureDate: \DateTimeImmutable, returnDate: \DateTimeImmutable}> $candidateDateWindows
     */
    public function withCandidateDateWindows(array $candidateDateWindows): self
    {
        return new self(
            originAirport: $this->originAirport,
            originCity: $this->originCity,
            destinationCity: $this->destinationCity,
            departureDate: $this->departureDate,
            returnDate: $this->returnDate,
            nights: $this->nights,
            adults: $this->adults,
            children: $this->children,
            infants: $this->infants,
            childAges: $this->childAges,
            budget: $this->budget,
            preferredCurrency: $this->preferredCurrency,
            hotelStarPreference: $this->hotelStarPreference,
            breakfastPreferred: $this->breakfastPreferred,
            flightCabin: $this->flightCabin,
            directFlightPreferred: $this->directFlightPreferred,
            activityCategories: $this->activityCategories,
            transferRequired: $this->transferRequired,
            dateMode: $this->dateMode,
            windowStart: $this->windowStart,
            windowEnd: $this->windowEnd,
            goal: $this->goal,
            candidateDateWindows: $candidateDateWindows,
            rooms: $this->rooms,
            destinationCountry: $this->destinationCountry,
            commercialDepartureAirport: $this->commercialDepartureAirport,
        );
    }

    /**
     * Resolves a country-only request onto one concrete destination city once
     * live search has established that city actually has usable inventory,
     * so downstream Flight/Hotel candidate building (which needs a concrete
     * destination airport/hotel set) can run. destinationCountry is kept so
     * the request never stops being recognized as belonging to that country.
     */
    public function withDestinationCity(City $city): self
    {
        return new self(
            originAirport: $this->originAirport,
            originCity: $this->originCity,
            destinationCity: $city,
            departureDate: $this->departureDate,
            returnDate: $this->returnDate,
            nights: $this->nights,
            adults: $this->adults,
            children: $this->children,
            infants: $this->infants,
            childAges: $this->childAges,
            budget: $this->budget,
            preferredCurrency: $this->preferredCurrency,
            hotelStarPreference: $this->hotelStarPreference,
            breakfastPreferred: $this->breakfastPreferred,
            flightCabin: $this->flightCabin,
            directFlightPreferred: $this->directFlightPreferred,
            activityCategories: $this->activityCategories,
            transferRequired: $this->transferRequired,
            dateMode: $this->dateMode,
            windowStart: $this->windowStart,
            windowEnd: $this->windowEnd,
            goal: $this->goal,
            candidateDateWindows: $this->candidateDateWindows,
            rooms: $this->rooms,
            destinationCountry: $this->destinationCountry ?? $city->getCountry(),
            commercialDepartureAirport: $this->commercialDepartureAirport,
        );
    }

    /**
     * Attaches a validated alternative commercial departure airport found by
     * CommercialDepartureResolver after a direct-origin search produced no
     * usable result. originAirport/originCity are left untouched — the
     * user's real origin is never overwritten.
     */
    public function withCommercialDepartureAirport(Airport $airport): self
    {
        return new self(
            originAirport: $this->originAirport,
            originCity: $this->originCity,
            destinationCity: $this->destinationCity,
            departureDate: $this->departureDate,
            returnDate: $this->returnDate,
            nights: $this->nights,
            adults: $this->adults,
            children: $this->children,
            infants: $this->infants,
            childAges: $this->childAges,
            budget: $this->budget,
            preferredCurrency: $this->preferredCurrency,
            hotelStarPreference: $this->hotelStarPreference,
            breakfastPreferred: $this->breakfastPreferred,
            flightCabin: $this->flightCabin,
            directFlightPreferred: $this->directFlightPreferred,
            activityCategories: $this->activityCategories,
            transferRequired: $this->transferRequired,
            dateMode: $this->dateMode,
            windowStart: $this->windowStart,
            windowEnd: $this->windowEnd,
            goal: $this->goal,
            candidateDateWindows: $this->candidateDateWindows,
            rooms: $this->rooms,
            destinationCountry: $this->destinationCountry,
            commercialDepartureAirport: $airport,
        );
    }

    public function passengerCount(): int
    {
        return $this->adults + $this->children + $this->infants;
    }
}
