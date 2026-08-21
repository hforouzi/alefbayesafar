<?php

namespace App\Modules\Hotel\ValueObject;

final readonly class HotelSearchSummary
{
    /**
     * @param HotelSearchResult[] $results
     */
    public function __construct(public array $results)
    {
    }

    /**
     * @return HotelCandidate[]
     */
    public function candidates(): array
    {
        $candidates = [];
        foreach ($this->results as $result) {
            foreach ($result->candidates as $candidate) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    public function hasFailures(): bool
    {
        foreach ($this->results as $result) {
            if (!$result->success) {
                return true;
            }
        }

        return false;
    }
}
