<?php

namespace App\Modules\Destination\ValueObject;

/**
 * @template T
 */
final readonly class PaginatedResult
{
    /**
     * @param T[] $items
     * @param array<string, scalar|null> $filters
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $pageSize,
        public array $filters = [],
    ) {
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->total / $this->pageSize));
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->pageSize;
    }

    public function firstItemNumber(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    public function lastItemNumber(): int
    {
        return min($this->total, $this->offset() + \count($this->items));
    }
}
