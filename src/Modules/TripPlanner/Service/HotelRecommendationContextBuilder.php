<?php

namespace App\Modules\TripPlanner\Service;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Repository\CityRepository;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelSourceReference;
use App\Modules\Hotel\Repository\HotelRepository;
use App\Modules\Hotel\Service\HotelSearchService;
use App\Modules\Hotel\ValueObject\HotelCandidate;
use App\Modules\Hotel\ValueObject\HotelSearchRequest;
use App\Modules\SearchSource\Provider\FirecrawlProvider;
use App\Modules\SearchSource\Provider\ProviderConfigurationException;
use App\Modules\SearchSource\Provider\ProviderRequestException;
use App\Modules\Tour\ValueObject\TourPricingCandidate;
use App\Modules\TripPlanner\ValueObject\HotelRecommendationContext;
use App\Modules\TripPlanner\ValueObject\HotelRecommendationSourceContext;
use App\Modules\TripPlanner\ValueObject\TripOption;
use Doctrine\ORM\EntityManagerInterface;

final readonly class HotelRecommendationContextBuilder
{
    private const MAX_HOTELS_PER_REQUEST = 5;
    private const CONTEXT_TTL = '7 days';

    public function __construct(
        private HotelRepository $hotelRepository,
        private CityRepository $cityRepository,
        private HotelSearchService $hotelSearchService,
        private FirecrawlProvider $firecrawlProvider,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function fromTourCandidate(TourPricingCandidate $candidate): ?HotelRecommendationContext
    {
        $hotel = $candidate->tourPackage?->getHotel() ?? $candidate->externalTourOffer?->getHotel();
        $hotelName = $hotel?->getName() ?? $candidate->externalTourOffer?->getHotelName() ?? $candidate->hotelSummary;
        if ($hotelName === null || trim($hotelName) === '') {
            return null;
        }

        $metadata = $candidate->externalTourOffer?->getMetadata() ?? [];
        $sourceHotel = $this->sourceHotel($metadata);
        $stars = $hotel?->getStars() ?? $this->intOrNull($metadata['hotelStars'] ?? $sourceHotel['star'] ?? $sourceHotel['stars'] ?? null);
        $reviewScore = $this->scalarString($sourceHotel['averageReviewScore'] ?? $sourceHotel['reviewScore'] ?? null);
        $reviewCount = $this->intOrNull($sourceHotel['reviewsCount'] ?? $sourceHotel['reviewCount'] ?? null);
        $sourceName = $candidate->externalTourOffer?->getSearchSource()?->getName();
        $embeddedSource = null;
        if ($sourceName !== null && ($reviewScore !== null || $reviewCount !== null || $candidate->externalTourOffer?->getFetchedAt() instanceof \DateTimeImmutable)) {
            $embeddedSource = new HotelRecommendationSourceContext(
                source: $sourceName,
                status: 'success',
                reviewScore: $reviewScore,
                reviewScoreScale: $reviewScore !== null ? '5' : null,
                reviewCount: $reviewCount,
                attributes: array_values(array_filter([
                    $stars !== null ? $stars . '-star hotel' : null,
                    $candidate->externalTourOffer?->getBoardType(),
                ])),
                fetchedAt: $candidate->externalTourOffer?->getFetchedAt(),
                freshness: $this->freshness($candidate->externalTourOffer?->getFetchedAt()),
                liveRefreshAttempted: false,
                liveRefreshStatus: 'embedded_tour_source',
            );
        }

        $warnings = [];
        if (!$hotel instanceof Hotel) {
            $warnings[] = 'Canonical hotel was not matched safely.';
        }
        if ($embeddedSource === null) {
            $warnings[] = 'No hotel review/source context is available.';
        }

        return new HotelRecommendationContext(
            hotelName: trim($hotelName),
            canonicalMatched: $hotel instanceof Hotel,
            canonicalHotelId: $hotel?->getId(),
            canonicalMatchStatus: $hotel instanceof Hotel ? 'matched' : 'unmatched',
            destinationCityId: ($candidate->tourPackage?->getDestinationCity() ?? $candidate->externalTourOffer?->getDestinationCity())?->getId(),
            stars: $stars,
            sourceReviewScore: $reviewScore,
            sourceReviewCount: $reviewCount,
            attributes: array_values(array_filter([
                $stars !== null ? $stars . '-star hotel' : null,
                $candidate->externalTourOffer?->getBoardType(),
            ])),
            sources: array_filter([$embeddedSource]),
            sourceAttribution: $sourceName,
            fetchedAt: $candidate->externalTourOffer?->getFetchedAt(),
            warnings: $warnings,
        );
    }

    /**
     * @param TripOption[] $options
     * @param array<string, mixed> $diagnostics
     *
     * @return TripOption[]
     */
    public function enrichShortlist(array $options, array &$diagnostics): array
    {
        $seen = [];
        $contexts = [];
        $enriched = [];

        foreach ($options as $option) {
            $context = $option->hotelRecommendationContext;
            if (!$context instanceof HotelRecommendationContext) {
                $enriched[] = $option;
                continue;
            }

            $key = $this->contextKey($context);
            if (!isset($contexts[$key]) && \count($contexts) < self::MAX_HOTELS_PER_REQUEST) {
                $contexts[$key] = $this->enrich($context);
                $seen[] = $contexts[$key];
            }

            $enriched[] = isset($contexts[$key]) ? $option->withHotelRecommendationContext($contexts[$key]) : $option;
        }

        $diagnostics['hotelRecommendationContext'] = [
            'maxHotels' => self::MAX_HOTELS_PER_REQUEST,
            'attemptedHotels' => \count($seen),
            'hotels' => array_map($this->diagnosticContext(...), $seen),
        ];

        return $enriched;
    }

    private function enrich(HotelRecommendationContext $context): HotelRecommendationContext
    {
        [$hotel, $matchStatus, $matchWarnings] = $this->resolveCanonicalHotel($context);
        $sources = $context->sources;
        $warnings = array_merge($context->warnings, $matchWarnings);
        $stars = $hotel?->getStars() ?? $context->stars;

        if ($hotel instanceof Hotel) {
            foreach ($hotel->getSourceReferences() as $reference) {
                $sources[] = $this->sourceReferenceContext($reference);
            }
        } else {
            $sources = array_merge($sources, $this->hotelSearchContexts($context));
        }

        if ($sources === []) {
            $warnings[] = 'hotel_review_context_unavailable';
        }
        if (!$this->hasReviewFacts($sources)) {
            $warnings[] = 'No hotel review/source context is available.';
        }
        foreach ($sources as $source) {
            foreach ($source->warnings as $warning) {
                $warnings[] = $warning;
            }
        }

        return $context->withEnrichment(
            canonicalHotelId: $hotel?->getId(),
            canonicalMatchStatus: $matchStatus,
            stars: $stars,
            sources: $this->deduplicateSources($sources),
            warnings: $warnings,
        );
    }

    /**
     * @param HotelRecommendationSourceContext[] $sources
     */
    private function hasReviewFacts(array $sources): bool
    {
        foreach ($sources as $source) {
            if ($source->reviewScore !== null || $source->reviewCount !== null) {
                return true;
            }
        }

        return false;
    }

    private function contextKey(HotelRecommendationContext $context): string
    {
        return implode('|', [
            $context->canonicalHotelId ?? '',
            $context->destinationCityId ?? '',
            $this->normalizeName($context->hotelName),
        ]);
    }

    /**
     * @return array{0: Hotel|null, 1: string, 2: string[]}
     */
    private function resolveCanonicalHotel(HotelRecommendationContext $context): array
    {
        if ($context->canonicalHotelId !== null) {
            $hotel = $this->hotelRepository->find($context->canonicalHotelId);
            if ($hotel instanceof Hotel) {
                return [$hotel, 'matched', []];
            }
        }

        if ($context->destinationCityId === null) {
            return [null, 'unmatched', ['Canonical hotel matching skipped because destination city is unavailable.']];
        }

        $city = $this->cityRepository->find($context->destinationCityId);
        if (!$city instanceof City) {
            return [null, 'unmatched', ['Canonical hotel matching skipped because destination city could not be loaded.']];
        }

        $matches = $this->hotelRepository->findActiveByNormalizedNameInCity($city, $context->hotelName, 2);
        if (\count($matches) === 1) {
            return [$matches[0], 'matched', []];
        }
        if (\count($matches) > 1) {
            return [null, 'ambiguous', ['Canonical hotel match is ambiguous; no hotel was selected automatically.']];
        }

        return [null, 'unmatched', ['Canonical hotel was not matched safely.']];
    }

    private function sourceReferenceContext(HotelSourceReference $reference): HotelRecommendationSourceContext
    {
        $metadata = $reference->getMetadata();
        $recommendation = \is_array($metadata['recommendationContext'] ?? null) ? $metadata['recommendationContext'] : [];
        $fetchedAt = $this->dateOrNull($recommendation['fetchedAt'] ?? null) ?? $reference->getLastSyncedAt();
        $reviewScore = $this->reviewScore($recommendation) ?? $this->reviewScore($metadata);
        $reviewCount = $this->reviewCount($recommendation) ?? $this->reviewCount($metadata);

        if ($this->isFresh($fetchedAt)) {
            return new HotelRecommendationSourceContext(
                source: $reference->getSource(),
                status: $reviewScore !== null || $reviewCount !== null ? 'success' : 'no_data',
                sourceHotelId: $reference->getExternalId(),
                sourceUrl: $reference->getSourceUrl(),
                reviewScore: $reviewScore,
                reviewScoreScale: $this->scalarString($recommendation['reviewScoreScale'] ?? $metadata['reviewScoreScale'] ?? null),
                reviewCount: $reviewCount,
                reviewSignals: $this->stringMap($recommendation['reviewSignals'] ?? []),
                attributes: $this->stringList($recommendation['attributes'] ?? []),
                fetchedAt: $fetchedAt,
                freshness: 'fresh',
                liveRefreshAttempted: false,
                liveRefreshStatus: 'fresh_cache',
                warnings: $reviewScore === null && $reviewCount === null ? ['Fresh source reference has no factual review score/count.'] : [],
            );
        }

        if ($reference->getSourceUrl() === null) {
            return new HotelRecommendationSourceContext(
                source: $reference->getSource(),
                status: $reviewScore !== null || $reviewCount !== null ? 'cached_fallback' : 'no_data',
                sourceHotelId: $reference->getExternalId(),
                reviewScore: $reviewScore,
                reviewScoreScale: $this->scalarString($recommendation['reviewScoreScale'] ?? $metadata['reviewScoreScale'] ?? null),
                reviewCount: $reviewCount,
                fetchedAt: $fetchedAt,
                freshness: $this->freshness($fetchedAt),
                liveRefreshAttempted: false,
                liveRefreshStatus: 'missing_source_url',
                warnings: ['Live hotel context refresh skipped because source URL is unavailable.'],
            );
        }

        try {
            return $this->refreshSourceReference($reference);
        } catch (ProviderConfigurationException|ProviderRequestException $exception) {
            $reference
                ->setSyncStatus(HotelSourceReference::STATUS_FAILED)
                ->setLastError($exception->getMessage());
            $this->entityManager->flush();

            return new HotelRecommendationSourceContext(
                source: $reference->getSource(),
                status: $reviewScore !== null || $reviewCount !== null ? 'cached_fallback' : 'provider_error',
                sourceHotelId: $reference->getExternalId(),
                sourceUrl: $reference->getSourceUrl(),
                reviewScore: $reviewScore,
                reviewScoreScale: $this->scalarString($recommendation['reviewScoreScale'] ?? $metadata['reviewScoreScale'] ?? null),
                reviewCount: $reviewCount,
                fetchedAt: $fetchedAt,
                freshness: $this->freshness($fetchedAt),
                liveRefreshAttempted: true,
                liveRefreshStatus: 'provider_error',
                warnings: ['Hotel context live refresh failed: ' . $exception->getMessage()],
            );
        }
    }

    private function refreshSourceReference(HotelSourceReference $reference): HotelRecommendationSourceContext
    {
        $payload = [
            'url' => $reference->getSourceUrl(),
            'formats' => [
                [
                    'type' => 'json',
                    'schema' => $this->reviewSchema(),
                    'prompt' => 'Extract only factual hotel context explicitly visible on this hotel page: hotel name, star rating, review score, review score scale, review count, address or neighborhood, breakfast/board facts, and structured category scores such as location, cleanliness, staff, facilities, breakfast, and value. Do not infer missing values. Do not summarize sentiment. Do not invent strengths or weaknesses.',
                ],
                'markdown',
            ],
        ];
        $response = $this->firecrawlProvider->request('POST', '/v2/scrape', $payload);
        $data = $this->jsonData($response);
        $markdown = $this->scalarString($response['data']['markdown'] ?? $response['markdown'] ?? null);
        $score = $this->reviewScore($data);
        $count = $this->reviewCount($data);
        $scale = $this->scalarString($data['reviewScoreScale'] ?? null);
        $accepted = ($score === null || $this->sourceEvidenceContains($markdown, $score))
            && ($count === null || $this->sourceEvidenceContains($markdown, (string) $count));
        $now = new \DateTimeImmutable();

        if (!$accepted || ($score === null && $count === null)) {
            $metadata = array_merge($reference->getMetadata(), [
                'recommendationContext' => [
                    'status' => 'NO_DATA',
                    'endpoint' => '/v2/scrape',
                    'method' => 'POST',
                    'url' => $reference->getSourceUrl(),
                    'fetchedAt' => $now->format(DATE_ATOM),
                    'topLevelKeys' => array_keys($response),
                    'dataKeys' => array_keys($data),
                ],
            ]);
            $reference
                ->setSyncStatus(HotelSourceReference::STATUS_SYNCED)
                ->setLastError(null)
                ->setMetadata($metadata)
                ->markSeen();
            $this->entityManager->flush();

            return new HotelRecommendationSourceContext(
                source: $reference->getSource(),
                status: 'no_data',
                sourceHotelId: $reference->getExternalId(),
                sourceUrl: $reference->getSourceUrl(),
                fetchedAt: $now,
                freshness: 'fresh',
                liveRefreshAttempted: true,
                liveRefreshStatus: 'no_data',
                warnings: ['Source responded, but no verifiable factual hotel review score/count was extracted.'],
            );
        }

        $attributes = array_values(array_filter([
            $this->scalarString($data['starRating'] ?? null) !== null ? $this->scalarString($data['starRating']) . '-star hotel' : null,
            $this->scalarString($data['address'] ?? $data['neighborhood'] ?? null),
            $this->scalarString($data['breakfast'] ?? $data['board'] ?? null),
        ]));
        $recommendation = [
            'status' => 'SUCCESS',
            'endpoint' => '/v2/scrape',
            'method' => 'POST',
            'url' => $reference->getSourceUrl(),
            'fetchedAt' => $now->format(DATE_ATOM),
            'reviewScore' => $score,
            'reviewScoreScale' => $scale,
            'reviewCount' => $count,
            'reviewSignals' => $this->stringMap($data['reviewSignals'] ?? []),
            'attributes' => $attributes,
            'topLevelKeys' => array_keys($response),
            'dataKeys' => array_keys($data),
        ];
        $reference
            ->setSyncStatus(HotelSourceReference::STATUS_SYNCED)
            ->setLastError(null)
            ->setMetadata(array_merge($reference->getMetadata(), ['recommendationContext' => $recommendation]))
            ->markSeen();
        $this->entityManager->flush();

        return new HotelRecommendationSourceContext(
            source: $reference->getSource(),
            status: 'success',
            sourceHotelId: $reference->getExternalId(),
            sourceUrl: $reference->getSourceUrl(),
            reviewScore: $score,
            reviewScoreScale: $scale,
            reviewCount: $count,
            reviewSignals: $recommendation['reviewSignals'],
            attributes: $attributes,
            fetchedAt: $now,
            freshness: 'fresh',
            liveRefreshAttempted: true,
            liveRefreshStatus: 'success',
        );
    }

    /**
     * @return HotelRecommendationSourceContext[]
     */
    private function hotelSearchContexts(HotelRecommendationContext $context): array
    {
        if ($context->destinationCityId === null) {
            return [new HotelRecommendationSourceContext(
                source: 'hotel_search',
                status: 'skipped',
                liveRefreshAttempted: false,
                liveRefreshStatus: 'missing_destination_city',
                warnings: ['Independent hotel source lookup skipped because destination city is unavailable.'],
            )];
        }

        $city = $this->cityRepository->find($context->destinationCityId);
        if (!$city instanceof City) {
            return [new HotelRecommendationSourceContext(
                source: 'hotel_search',
                status: 'skipped',
                liveRefreshAttempted: false,
                liveRefreshStatus: 'missing_destination_city',
                warnings: ['Independent hotel source lookup skipped because destination city could not be loaded.'],
            )];
        }

        $contexts = [];
        try {
            $summary = $this->hotelSearchService->search(new HotelSearchRequest($context->hotelName, $city, null, 3));
        } catch (\Throwable $exception) {
            return [new HotelRecommendationSourceContext(
                source: 'hotel_search',
                status: 'provider_error',
                liveRefreshAttempted: true,
                liveRefreshStatus: 'provider_error',
                warnings: ['Independent hotel source lookup failed: ' . $exception->getMessage()],
            )];
        }

        foreach ($summary->results as $result) {
            $matched = array_values(array_filter(
                $result->candidates,
                fn (HotelCandidate $candidate): bool => $this->normalizeName($candidate->name) === $this->normalizeName($context->hotelName),
            ));
            $candidate = $matched[0] ?? null;
            $contexts[] = new HotelRecommendationSourceContext(
                source: $result->source->getName(),
                status: $result->success ? ($candidate instanceof HotelCandidate ? 'success' : 'no_data') : 'provider_error',
                sourceHotelId: $candidate?->externalId,
                sourceUrl: $candidate?->sourceUrl,
                reviewScore: $this->reviewScore($candidate?->rawData ?? []),
                reviewScoreScale: $this->scalarString(($candidate?->rawData ?? [])['reviewScoreScale'] ?? null),
                reviewCount: $this->reviewCount($candidate?->rawData ?? []),
                attributes: array_values(array_filter([
                    $candidate?->stars !== null ? $candidate->stars . '-star hotel' : null,
                    $candidate?->address,
                ])),
                fetchedAt: new \DateTimeImmutable(),
                freshness: 'fresh',
                liveRefreshAttempted: true,
                liveRefreshStatus: $result->success ? 'hotel_search' : 'provider_error',
                warnings: $result->success
                    ? ($candidate instanceof HotelCandidate ? ['Independent hotel source found a same-name listing; canonical import/link remains manual until safely confirmed.'] : ['Independent hotel source returned no exact hotel match.'])
                    : $result->errors,
            );
        }

        if ($contexts === []) {
            $contexts[] = new HotelRecommendationSourceContext(
                source: 'hotel_search',
                status: 'skipped',
                liveRefreshAttempted: false,
                liveRefreshStatus: 'no_configured_sources',
                warnings: ['No enabled hotel SearchSource is configured for independent hotel context.'],
            );
        }

        return $contexts;
    }

    /**
     * @param HotelRecommendationSourceContext[] $sources
     *
     * @return HotelRecommendationSourceContext[]
     */
    private function deduplicateSources(array $sources): array
    {
        $unique = [];
        foreach ($sources as $source) {
            $key = implode('|', [$source->source, $source->sourceHotelId ?? '', $source->sourceUrl ?? '', $source->status]);
            $unique[$key] ??= $source;
        }

        return array_values($unique);
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnosticContext(HotelRecommendationContext $context): array
    {
        return [
            'hotel' => $context->hotelName,
            'canonicalMatchStatus' => $context->canonicalMatchStatus,
            'canonicalHotelId' => $context->canonicalHotelId,
            'sourcesAttempted' => array_map(static fn (HotelRecommendationSourceContext $source): array => [
                'source' => $source->source,
                'status' => $source->status,
                'sourceUrl' => $source->sourceUrl,
                'reviewScore' => $source->reviewScore,
                'reviewScoreScale' => $source->reviewScoreScale,
                'reviewCount' => $source->reviewCount,
                'fetchedAt' => $source->fetchedAt?->format(DATE_ATOM),
                'freshness' => $source->freshness,
                'liveRefreshAttempted' => $source->liveRefreshAttempted,
                'liveRefreshStatus' => $source->liveRefreshStatus,
                'warnings' => $source->warnings,
            ], $context->sources),
            'warnings' => $context->warnings,
        ];
    }

    private function isFresh(?\DateTimeImmutable $fetchedAt): bool
    {
        return $fetchedAt instanceof \DateTimeImmutable && $fetchedAt >= new \DateTimeImmutable('-' . self::CONTEXT_TTL);
    }

    private function freshness(?\DateTimeImmutable $fetchedAt): string
    {
        if (!$fetchedAt instanceof \DateTimeImmutable) {
            return 'unknown';
        }

        return $this->isFresh($fetchedAt) ? 'fresh' : 'stale';
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<string, mixed>
     */
    private function jsonData(array $response): array
    {
        foreach ([
            $response['data']['json'] ?? null,
            $response['json'] ?? null,
            $response['data'] ?? null,
        ] as $data) {
            if (\is_array($data)) {
                return $data;
            }
        }

        return [];
    }

    /** @return array<string, mixed> */
    private function reviewSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'hotelName' => ['type' => ['string', 'null']],
                'starRating' => ['type' => ['number', 'string', 'null']],
                'reviewScore' => ['type' => ['number', 'string', 'null']],
                'reviewScoreScale' => ['type' => ['number', 'string', 'null']],
                'reviewCount' => ['type' => ['integer', 'string', 'null']],
                'address' => ['type' => ['string', 'null']],
                'neighborhood' => ['type' => ['string', 'null']],
                'breakfast' => ['type' => ['string', 'null']],
                'board' => ['type' => ['string', 'null']],
                'reviewSignals' => [
                    'type' => 'object',
                    'additionalProperties' => ['type' => ['string', 'number', 'null']],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private function sourceHotel(array $metadata): array
    {
        $raw = \is_array($metadata['rawProviderOffer'] ?? null) ? $metadata['rawProviderOffer'] : [];
        $hotels = \is_array($raw['hotels'] ?? null) && array_is_list($raw['hotels']) ? $raw['hotels'] : [];
        $first = \is_array($hotels[0] ?? null) ? $hotels[0] : [];
        $hotel = \is_array($first['hotel'] ?? null) ? $first['hotel'] : $first;

        return $hotel;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function reviewScore(array $metadata): ?string
    {
        foreach (['reviewScore', 'score', 'rating', 'guestRating', 'averageReviewScore'] as $key) {
            $value = $metadata[$key] ?? null;
            if (\is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function reviewCount(array $metadata): ?int
    {
        foreach (['reviewCount', 'reviews', 'ratingsCount', 'reviewsCount'] as $key) {
            $value = $metadata[$key] ?? null;
            if (\is_int($value)) {
                return $value >= 0 ? $value : null;
            }
            if (\is_string($value) && ctype_digit($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    private function intOrNull(mixed $value): ?int
    {
        if (\is_int($value)) {
            return $value;
        }
        if (\is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function dateOrNull(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if (!\is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return string[]
     */
    private function stringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(fn (mixed $item): ?string => $this->scalarString($item), $value)));
    }

    /**
     * @return array<string, string>
     */
    private function stringMap(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $key => $item) {
            $string = $this->scalarString($item);
            if ($string !== null) {
                $map[(string) $key] = $string;
            }
        }

        return $map;
    }

    private function sourceEvidenceContains(?string $markdown, string $value): bool
    {
        if ($markdown === null || trim($markdown) === '') {
            return false;
        }

        $needle = preg_replace('/[^0-9a-z]+/i', '', $value) ?? '';
        $haystack = preg_replace('/[^0-9a-z]+/i', '', $markdown) ?? '';

        return $needle !== '' && str_contains(mb_strtolower($haystack), mb_strtolower($needle));
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if (\is_string($converted) && $converted !== '') {
            $name = $converted;
        }
        $name = preg_replace('/\b(hotel|hotels|resort|apartments?|suites?|the|and)\b/u', ' ', $name) ?? $name;
        $name = preg_replace('/[^a-z0-9]+/u', ' ', $name) ?? $name;

        return trim((string) preg_replace('/\s+/', ' ', $name));
    }

    private function scalarString(mixed $value): ?string
    {
        if (!\is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
