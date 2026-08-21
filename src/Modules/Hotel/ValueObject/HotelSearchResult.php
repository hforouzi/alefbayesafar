<?php

namespace App\Modules\Hotel\ValueObject;

use App\Modules\SearchSource\Entity\SearchSource;

final readonly class HotelSearchResult
{
    /**
     * @param HotelCandidate[] $candidates
     * @param string[] $errors
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public SearchSource $source,
        public bool $success,
        public array $candidates = [],
        public array $errors = [],
        public array $metadata = [],
    ) {
    }

    /**
     * @param HotelCandidate[] $candidates
     * @param array<string, mixed> $metadata
     */
    public static function success(SearchSource $source, array $candidates, array $metadata = []): self
    {
        return new self($source, true, $candidates, [], $metadata);
    }

    /**
     * @param string[] $errors
     * @param array<string, mixed> $metadata
     */
    public static function failure(SearchSource $source, array $errors, array $metadata = []): self
    {
        return new self($source, false, [], $errors, $metadata);
    }
}
