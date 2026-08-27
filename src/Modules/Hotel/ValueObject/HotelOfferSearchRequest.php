<?php

namespace App\Modules\Hotel\ValueObject;

final readonly class HotelOfferSearchRequest
{
    public const MAX_CHILD_AGE = 17;

    /**
     * @var int[]
     */
    public array $childrenAges;

    /**
     * @param array<int, mixed> $childrenAges
     */
    public function __construct(
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $adults,
        public int $children = 0,
        array $childrenAges = [],
    ) {
        if ($this->checkOut <= $this->checkIn) {
            throw new \InvalidArgumentException('Hotel offer search check-out date must be after check-in date.');
        }

        if ($this->adults < 1) {
            throw new \InvalidArgumentException('Hotel offer search must include at least one adult.');
        }

        if ($this->children < 0) {
            throw new \InvalidArgumentException('Hotel offer search children count cannot be negative.');
        }

        $this->childrenAges = self::normalizeChildrenAges($childrenAges);

        if (\count($this->childrenAges) !== $this->children) {
            throw new \InvalidArgumentException('Hotel offer search must include one age for each child.');
        }
    }

    /**
     * @param array<int, mixed> $childrenAges
     *
     * @return int[]
     */
    public static function normalizeChildrenAges(array $childrenAges): array
    {
        $normalized = [];
        foreach ($childrenAges as $age) {
            if (!\is_int($age)) {
                throw new \InvalidArgumentException('Hotel offer search child ages must be integers.');
            }

            if ($age < 0 || $age > self::MAX_CHILD_AGE) {
                throw new \InvalidArgumentException(sprintf('Hotel offer search child ages must be between 0 and %d.', self::MAX_CHILD_AGE));
            }

            $normalized[] = $age;
        }

        sort($normalized, SORT_NUMERIC);

        return $normalized;
    }
}
