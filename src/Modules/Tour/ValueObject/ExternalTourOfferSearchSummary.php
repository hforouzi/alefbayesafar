<?php

namespace App\Modules\Tour\ValueObject;

use App\Modules\Tour\Enum\ExternalTourOfferSearchStatus;

final readonly class ExternalTourOfferSearchSummary
{
    /**
     * @param ExternalTourOfferSearchResult[] $results
     */
    public function __construct(public array $results)
    {
    }

    /**
     * @return ExternalTourOfferSearchResult[]
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @return ExternalTourOfferCandidate[]
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

    public function hasStatus(ExternalTourOfferSearchStatus $status): bool
    {
        foreach ($this->results as $result) {
            if ($result->status === $status) {
                return true;
            }
        }

        return false;
    }
}
