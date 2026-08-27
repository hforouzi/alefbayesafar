<?php

namespace App\Modules\Hotel\ValueObject;

use App\Modules\Hotel\Enum\HotelOfferSearchStatus;
use App\Modules\SearchSource\Entity\SearchSource;

final readonly class HotelOfferSearchResult
{
    /**
     * @param HotelOfferCandidate[] $candidates
     * @param string[] $errors
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public SearchSource $source,
        public bool $success,
        public array $candidates = [],
        public array $errors = [],
        public array $metadata = [],
        public HotelOfferSearchStatus $status = HotelOfferSearchStatus::NO_DATA,
    ) {
    }

    /**
     * @param HotelOfferCandidate[] $candidates
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
            $candidates !== [] ? HotelOfferSearchStatus::OFFERS_FOUND : HotelOfferSearchStatus::NO_DATA,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function soldOut(SearchSource $source, array $metadata = []): self
    {
        return new self($source, true, [], [], $metadata, HotelOfferSearchStatus::SOLD_OUT);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function noData(SearchSource $source, array $metadata = []): self
    {
        return new self($source, true, [], [], $metadata, HotelOfferSearchStatus::NO_DATA);
    }

    /**
     * @param string[] $errors
     * @param array<string, mixed> $metadata
     */
    public static function failure(SearchSource $source, array $errors, array $metadata = []): self
    {
        return new self($source, false, [], $errors, $metadata, HotelOfferSearchStatus::PROVIDER_ERROR);
    }
}
