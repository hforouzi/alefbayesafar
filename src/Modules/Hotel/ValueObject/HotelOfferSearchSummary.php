<?php

namespace App\Modules\Hotel\ValueObject;

use App\Modules\Hotel\Enum\HotelOfferSearchStatus;

final readonly class HotelOfferSearchSummary
{
    /**
     * @param HotelOfferSearchResult[] $results
     */
    public function __construct(public array $results)
    {
    }

    /**
     * @return HotelOfferSearchResult[]
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @return HotelOfferCandidate[]
     */
    public function getCandidates(): array
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
            if ($result->status->isProviderFailure()) {
                return true;
            }
        }

        return false;
    }

    public function hasStatus(HotelOfferSearchStatus $status): bool
    {
        foreach ($this->results as $result) {
            if ($result->status === $status) {
                return true;
            }
        }

        return false;
    }
}
