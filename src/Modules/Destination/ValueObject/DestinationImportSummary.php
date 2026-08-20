<?php

namespace App\Modules\Destination\ValueObject;

use App\Modules\Destination\Entity\DestinationImportRun;

final class DestinationImportSummary
{
    private const MAX_ITEM_DETAILS = 200;

    /**
     * @param array<int, array<string, mixed>> $items
     * @param string[] $errors
     */
    public function __construct(
        public readonly DestinationImportRun $run,
        public int $found = 0,
        public int $created = 0,
        public int $updated = 0,
        public int $unchanged = 0,
        public int $skipped = 0,
        public int $duplicates = 0,
        public int $failed = 0,
        public array $items = [],
        public array $errors = [],
        public int $itemsSuppressed = 0,
    ) {
    }

    /**
     * @param array<string, mixed> $item
     */
    public function addItem(array $item): void
    {
        if (\count($this->items) >= self::MAX_ITEM_DETAILS) {
            $this->itemsSuppressed++;

            return;
        }

        $this->items[] = $item;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
        $this->failed++;
    }

    public function status(): string
    {
        if ($this->found === 0 && $this->failed > 0) {
            return DestinationImportRun::STATUS_FAILED;
        }

        return $this->failed > 0 ? DestinationImportRun::STATUS_COMPLETED_WITH_ERRORS : DestinationImportRun::STATUS_COMPLETED;
    }
}
