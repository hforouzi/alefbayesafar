<?php

namespace App\Modules\Flight\ValueObject;

use App\Modules\Flight\Enum\FlightOfferSearchStatus;

final readonly class FlightOfferSearchSummary
{
    /**
     * @param FlightOfferSearchResult[] $results
     */
    public function __construct(public array $results)
    {
    }

    /**
     * @return FlightOfferSearchResult[]
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @return FlightOfferCandidate[]
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

    public function hasStatus(FlightOfferSearchStatus $status): bool
    {
        foreach ($this->results as $result) {
            if ($result->status === $status) {
                return true;
            }
        }

        return false;
    }
}
