<?php

namespace App\Modules\Destination\ValueObject;

/**
 * Result of one destination-insight provider call. Mirrors the
 * success/failure wrapper pattern used by HotelSearchResult and
 * DestinationProviderResult, so an upstream provider failure is never
 * silently reported as "nothing found" (AGENTS.md #20).
 */
final readonly class DestinationInsightResult
{
    /**
     * @param DestinationInsight[] $insights
     * @param string[] $errors
     */
    private function __construct(
        public bool $success,
        public array $insights,
        public array $errors = [],
    ) {
    }

    /**
     * @param DestinationInsight[] $insights
     */
    public static function success(array $insights): self
    {
        return new self(true, $insights);
    }

    /**
     * @param string[] $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, [], $errors);
    }
}
