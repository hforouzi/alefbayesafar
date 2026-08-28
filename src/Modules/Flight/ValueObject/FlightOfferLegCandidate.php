<?php

namespace App\Modules\Flight\ValueObject;

use App\Modules\Flight\Enum\FlightDirection;

final readonly class FlightOfferLegCandidate
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public FlightDirection $direction,
        public int $segmentIndex,
        public ?string $airlineName,
        public ?string $airlineIata,
        public ?string $airlineIcao,
        public string $originIata,
        public string $destinationIata,
        public ?string $flightNumber,
        public \DateTimeImmutable $departureAt,
        public \DateTimeImmutable $arrivalAt,
        public ?int $durationMinutes = null,
        public ?string $aircraft = null,
        public array $metadata = [],
    ) {
        if ($segmentIndex < 0) {
            throw new \InvalidArgumentException('Flight leg candidate segment index cannot be negative.');
        }

        if (preg_match('/^[A-Z]{3}$/', $originIata) !== 1 || preg_match('/^[A-Z]{3}$/', $destinationIata) !== 1) {
            throw new \InvalidArgumentException('Flight leg candidate airport IATA codes must be three uppercase letters.');
        }

        if ($originIata === $destinationIata) {
            throw new \InvalidArgumentException('Flight leg candidate destination must differ from origin.');
        }

        if ($departureAt >= $arrivalAt) {
            throw new \InvalidArgumentException('Flight leg candidate arrival must be after departure.');
        }
    }
}
