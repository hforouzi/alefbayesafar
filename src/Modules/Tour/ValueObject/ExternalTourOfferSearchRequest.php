<?php

namespace App\Modules\Tour\ValueObject;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;

final readonly class ExternalTourOfferSearchRequest
{
    /**
     * @param int[] $childrenAges
     */
    public function __construct(
        public ?Airport $originAirport,
        public City $destinationCity,
        public ?\DateTimeImmutable $departureDate,
        public ?\DateTimeImmutable $returnDate,
        public ?\DateTimeImmutable $validFrom,
        public ?\DateTimeImmutable $validTo,
        public ?int $nights,
        public int $adults,
        public int $children,
        public int $infants,
        public array $childrenAges = [],
        public ?string $budget = null,
        public ?string $currency = null,
        public int $rooms = 1,
    ) {
        if ($returnDate instanceof \DateTimeImmutable && $departureDate instanceof \DateTimeImmutable && $returnDate < $departureDate) {
            throw new \InvalidArgumentException('External tour search return date must be after or equal to departure date.');
        }
        if ($validTo instanceof \DateTimeImmutable && $validFrom instanceof \DateTimeImmutable && $validTo < $validFrom) {
            throw new \InvalidArgumentException('External tour search validity end must be after or equal to validity start.');
        }
        if ($nights !== null && $nights < 1) {
            throw new \InvalidArgumentException('External tour search nights must be positive when provided.');
        }
        if (!$departureDate instanceof \DateTimeImmutable && !$validFrom instanceof \DateTimeImmutable && $nights === null) {
            throw new \InvalidArgumentException('External tour search requires a departure date, validity range, or nights filter.');
        }
        if ($adults < 1) {
            throw new \InvalidArgumentException('External tour search must include at least one adult.');
        }
        if ($children < 0 || $infants < 0) {
            throw new \InvalidArgumentException('External tour search child and infant counts cannot be negative.');
        }
        if ($children === 0 && $childrenAges !== []) {
            throw new \InvalidArgumentException('External tour search child ages must be empty when no children are included.');
        }
        if ($children > 0 && \count($childrenAges) !== $children) {
            throw new \InvalidArgumentException('External tour search child ages count must match children count.');
        }
        if ($currency !== null && preg_match('/^[A-Z]{3}$/', strtoupper($currency)) !== 1) {
            throw new \InvalidArgumentException('External tour search currency must be a 3-letter ISO code.');
        }
        if ($budget !== null && TourMoney::normalize($budget) === null) {
            throw new \InvalidArgumentException('External tour search budget must be an unambiguous decimal amount.');
        }
        if ($rooms < 1) {
            throw new \InvalidArgumentException('External tour search rooms must be at least 1.');
        }
    }

    /**
     * @return array{originAirport: string|null, originCity: int|null, destinationCity: int|null, destinationName: string, departureDate: string|null, returnDate: string|null, validFrom: string|null, validTo: string|null, nights: int|null, adults: int, children: int, infants: int, childrenAges: int[], budget: string|null, currency: string|null, rooms: int}
     */
    public function context(): array
    {
        return [
            'originAirport' => $this->originAirport?->getIataCode(),
            'originCity' => $this->originAirport?->getCity()?->getId(),
            'destinationCity' => $this->destinationCity->getId(),
            'destinationName' => $this->destinationCity->getName(),
            'departureDate' => $this->departureDate?->format('Y-m-d'),
            'returnDate' => $this->returnDate?->format('Y-m-d'),
            'validFrom' => $this->validFrom?->format('Y-m-d'),
            'validTo' => $this->validTo?->format('Y-m-d'),
            'nights' => $this->nights,
            'adults' => $this->adults,
            'children' => $this->children,
            'infants' => $this->infants,
            'childrenAges' => array_values(array_map('intval', $this->childrenAges)),
            'budget' => $this->budget !== null ? TourMoney::normalize($this->budget) : null,
            'currency' => $this->currency !== null ? strtoupper($this->currency) : null,
            'rooms' => $this->rooms,
        ];
    }

    public function contextHash(): string
    {
        return hash('sha256', json_encode($this->context(), JSON_THROW_ON_ERROR));
    }
}
