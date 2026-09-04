<?php

namespace App\Modules\Flight\ValueObject;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\Flight\Enum\FlightTripType;

final readonly class FlightOfferSearchRequest
{
    public FlightTripType $tripType;

    public function __construct(
        public Airport $originAirport,
        public Airport $destinationAirport,
        public \DateTimeImmutable $departureDate,
        public ?\DateTimeImmutable $returnDate,
        public int $adults,
        public int $children,
        public int $infants,
        public FlightCabinClass $cabinClass,
        public ?bool $directOnly = null,
    ) {
        $this->tripType = $returnDate instanceof \DateTimeImmutable ? FlightTripType::ROUND_TRIP : FlightTripType::ONE_WAY;

        if ($originAirport === $destinationAirport || ($originAirport->getId() !== null && $originAirport->getId() === $destinationAirport->getId())) {
            throw new \InvalidArgumentException('Flight search origin and destination must differ.');
        }

        if ($this->airportCode($originAirport) === null || $this->airportCode($destinationAirport) === null) {
            throw new \InvalidArgumentException('Flight search airports must have IATA codes.');
        }

        if ($returnDate instanceof \DateTimeImmutable && $returnDate < $departureDate) {
            throw new \InvalidArgumentException('Flight search return date must be after or equal to departure date.');
        }

        if ($adults < 1) {
            throw new \InvalidArgumentException('Flight search must include at least one adult.');
        }

        if ($children < 0) {
            throw new \InvalidArgumentException('Flight search children count cannot be negative.');
        }

        if ($infants < 0) {
            throw new \InvalidArgumentException('Flight search infants count cannot be negative.');
        }

        if ($infants > $adults) {
            throw new \InvalidArgumentException('Flight search infants cannot exceed adult count.');
        }
    }

    /**
     * @return array{origin: string, destination: string, departureDate: string, returnDate: string|null, tripType: string, adults: int, children: int, infants: int, cabinClass: string, directOnly: bool|null}
     */
    public function context(): array
    {
        return [
            'origin' => (string) $this->originAirport->getIataCode(),
            'destination' => (string) $this->destinationAirport->getIataCode(),
            'departureDate' => $this->departureDate->format('Y-m-d'),
            'returnDate' => $this->returnDate?->format('Y-m-d'),
            'tripType' => $this->tripType->value,
            'adults' => $this->adults,
            'children' => $this->children,
            'infants' => $this->infants,
            'cabinClass' => $this->cabinClass->value,
            'directOnly' => $this->directOnly,
        ];
    }

    public function contextHash(): string
    {
        return hash('sha256', json_encode($this->context(), JSON_THROW_ON_ERROR));
    }

    private function airportCode(Airport $airport): ?string
    {
        $code = $airport->getIataCode();

        return \is_string($code) && preg_match('/^[A-Z]{3}$/', $code) === 1 ? $code : null;
    }
}
