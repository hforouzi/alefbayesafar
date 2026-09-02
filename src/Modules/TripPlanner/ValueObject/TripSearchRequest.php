<?php

namespace App\Modules\TripPlanner\ValueObject;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Flight\Enum\FlightCabinClass;

final readonly class TripSearchRequest
{
    /**
     * @param int[] $childAges
     * @param string[] $activityCategories
     */
    public function __construct(
        public ?Airport $originAirport,
        public ?City $originCity,
        public City $destinationCity,
        public \DateTimeImmutable $departureDate,
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
    ) {
        if ($this->adults < 1) {
            throw new \InvalidArgumentException('At least one adult is required.');
        }
        if ($this->children < 0 || $this->infants < 0) {
            throw new \InvalidArgumentException('Children and infants cannot be negative.');
        }
        if (\count($this->childAges) !== $this->children) {
            throw new \InvalidArgumentException('Child ages must match the number of children.');
        }
        if ($this->returnDate instanceof \DateTimeImmutable && $this->returnDate < $this->departureDate) {
            throw new \InvalidArgumentException('Return date must be after departure date.');
        }
        if ($this->nights !== null && $this->nights < 1) {
            throw new \InvalidArgumentException('Nights must be at least 1.');
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
    }

    public function checkOutDate(): \DateTimeImmutable
    {
        if ($this->returnDate instanceof \DateTimeImmutable) {
            return $this->returnDate;
        }

        return $this->departureDate->modify('+' . max(1, $this->nights ?? 1) . ' days');
    }

    public function nightsOrDerived(): int
    {
        if ($this->nights !== null) {
            return $this->nights;
        }

        return max(1, (int) $this->departureDate->diff($this->checkOutDate())->format('%a'));
    }

    public function passengerCount(): int
    {
        return $this->adults + $this->children + $this->infants;
    }
}
