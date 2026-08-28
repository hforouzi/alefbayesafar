<?php

namespace App\Modules\Flight\ValueObject;

use App\Modules\Flight\Enum\FlightOfferSearchStatus;
use App\Modules\SearchSource\Entity\SearchSource;

final readonly class FlightOfferSearchResult
{
    /**
     * @param FlightOfferCandidate[] $candidates
     * @param string[] $errors
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public SearchSource $source,
        public bool $success,
        public array $candidates = [],
        public array $errors = [],
        public array $metadata = [],
        public FlightOfferSearchStatus $status = FlightOfferSearchStatus::NO_DATA,
    ) {
    }

    /**
     * @param FlightOfferCandidate[] $candidates
     * @param array<string, mixed> $metadata
     */
    public static function success(SearchSource $source, array $candidates, array $metadata = []): self
    {
        return new self(
            $source,
            true,
            $candidates,
            [],
            $metadata,
            $candidates !== [] ? FlightOfferSearchStatus::OFFERS_FOUND : FlightOfferSearchStatus::NO_DATA,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function noResults(SearchSource $source, array $metadata = []): self
    {
        return new self($source, true, [], [], $metadata, FlightOfferSearchStatus::NO_RESULTS);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function noData(SearchSource $source, array $metadata = []): self
    {
        return new self($source, true, [], [], $metadata, FlightOfferSearchStatus::NO_DATA);
    }

    /**
     * @param string[] $errors
     * @param array<string, mixed> $metadata
     */
    public static function failure(SearchSource $source, array $errors, array $metadata = []): self
    {
        return new self($source, false, [], $errors, $metadata, FlightOfferSearchStatus::PROVIDER_ERROR);
    }
}
