<?php

namespace App\Modules\Destination\ValueObject;

/**
 * One factual destination-advice item (shopping mall, market, attraction,
 * restaurant, museum, etc.) sourced from a real external provider.
 *
 * Never AI-generated and never fabricated: every field must come from an
 * actual provider response. `rating`/`reviewCount` stay null when the
 * source did not expose them — they are never guessed or averaged.
 */
final readonly class DestinationInsight
{
    /**
     * @param array<string, string> $factualSummaryFields extra factual bits pulled verbatim from the source (e.g. a short snippet), never AI-invented
     */
    public function __construct(
        public string $title,
        public string $category,
        public string $city,
        public string $source,
        public ?string $rating = null,
        public ?int $reviewCount = null,
        public ?string $sourceUrl = null,
        public array $factualSummaryFields = [],
        public \DateTimeImmutable $fetchedAt = new \DateTimeImmutable(),
    ) {
    }
}
