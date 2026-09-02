<?php

namespace App\Modules\Tour\ValueObject;

use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\Tour\Enum\ExternalTourOfferSearchStatus;

final readonly class ExternalTourOfferSearchResult
{
    /**
     * @param ExternalTourOfferCandidate[] $candidates
     * @param string[] $errors
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public SearchSource $source,
        public bool $success,
        public array $candidates = [],
        public array $errors = [],
        public array $metadata = [],
        public ExternalTourOfferSearchStatus $status = ExternalTourOfferSearchStatus::NO_DATA,
    ) {
    }

    /**
     * @param ExternalTourOfferCandidate[] $candidates
     * @param array<string, mixed> $metadata
     */
    public static function success(SearchSource $source, array $candidates, array $metadata = []): self
    {
        return new self($source, true, $candidates, [], $metadata, $candidates !== [] ? ExternalTourOfferSearchStatus::OFFERS_FOUND : ExternalTourOfferSearchStatus::NO_DATA);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function noResults(SearchSource $source, array $metadata = []): self
    {
        return new self($source, true, [], [], $metadata, ExternalTourOfferSearchStatus::NO_RESULTS);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function noData(SearchSource $source, array $metadata = []): self
    {
        return new self($source, true, [], [], $metadata, ExternalTourOfferSearchStatus::NO_DATA);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function skipped(SearchSource $source, array $metadata = []): self
    {
        return new self($source, true, [], [], $metadata, ExternalTourOfferSearchStatus::SKIPPED);
    }

    /**
     * @param string[] $errors
     * @param array<string, mixed> $metadata
     */
    public static function failure(SearchSource $source, array $errors, array $metadata = []): self
    {
        return new self($source, false, [], $errors, $metadata, ExternalTourOfferSearchStatus::PROVIDER_ERROR);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self($this->source, $this->success, $this->candidates, $this->errors, $this->metadata + $metadata, $this->status);
    }
}
