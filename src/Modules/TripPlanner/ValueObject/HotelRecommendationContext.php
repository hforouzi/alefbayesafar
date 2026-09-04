<?php

namespace App\Modules\TripPlanner\ValueObject;

final readonly class HotelRecommendationContext
{
    /**
     * @param string[] $attributes
     * @param HotelRecommendationSourceContext[] $sources
     * @param string[] $warnings
     */
    public function __construct(
        public string $hotelName,
        public bool $canonicalMatched,
        public ?int $canonicalHotelId = null,
        public string $canonicalMatchStatus = 'unmatched',
        public ?int $destinationCityId = null,
        public ?int $stars = null,
        public ?string $sourceReviewScore = null,
        public ?int $sourceReviewCount = null,
        public array $attributes = [],
        public array $sources = [],
        public ?string $sourceAttribution = null,
        public ?\DateTimeImmutable $fetchedAt = null,
        public array $warnings = [],
    ) {
    }

    /**
     * @param HotelRecommendationSourceContext[] $sources
     * @param string[] $warnings
     */
    public function withEnrichment(?int $canonicalHotelId, string $canonicalMatchStatus, ?int $stars, array $sources, array $warnings): self
    {
        $primary = $this->primarySource($sources);

        return new self(
            hotelName: $this->hotelName,
            canonicalMatched: $canonicalMatchStatus === 'matched',
            canonicalHotelId: $canonicalHotelId,
            canonicalMatchStatus: $canonicalMatchStatus,
            destinationCityId: $this->destinationCityId,
            stars: $stars,
            sourceReviewScore: $primary?->reviewScore ?? $this->sourceReviewScore,
            sourceReviewCount: $primary?->reviewCount ?? $this->sourceReviewCount,
            attributes: $this->attributes,
            sources: $sources,
            sourceAttribution: $primary?->source ?? $this->sourceAttribution,
            fetchedAt: $primary?->fetchedAt ?? $this->fetchedAt,
            warnings: array_values(array_unique($warnings)),
        );
    }

    /**
     * @param HotelRecommendationSourceContext[] $sources
     */
    private function primarySource(array $sources): ?HotelRecommendationSourceContext
    {
        foreach ($sources as $source) {
            if ($source->reviewScore !== null || $source->reviewCount !== null) {
                return $source;
            }
        }

        return $sources[0] ?? null;
    }
}
