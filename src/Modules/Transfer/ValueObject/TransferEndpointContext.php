<?php

namespace App\Modules\Transfer\ValueObject;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Hotel\Entity\Hotel;

/**
 * One side (origin or destination) of a transfer search. Exactly one of
 * airport/city/hotel must be set, mirroring TransferProduct's own endpoint
 * fields so the resolver can match against them directly.
 */
final readonly class TransferEndpointContext
{
    private function __construct(
        public ?Airport $airport,
        public ?City $city,
        public ?Hotel $hotel,
    ) {
        $set = array_filter([$airport, $city, $hotel], static fn (mixed $value): bool => $value !== null);
        if (\count($set) !== 1) {
            throw new \InvalidArgumentException('A transfer endpoint must reference exactly one of airport, city, or hotel.');
        }
    }

    public static function forAirport(Airport $airport): self
    {
        return new self($airport, null, null);
    }

    public static function forCity(City $city): self
    {
        return new self(null, $city, null);
    }

    public static function forHotel(Hotel $hotel): self
    {
        return new self(null, null, $hotel);
    }

    public function label(): string
    {
        return (string) ($this->airport ?? $this->city ?? $this->hotel);
    }
}
