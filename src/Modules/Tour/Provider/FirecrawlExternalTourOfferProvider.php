<?php

namespace App\Modules\Tour\Provider;

use App\Modules\Hotel\Enum\HotelBoardType;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Provider\FirecrawlProvider;
use App\Modules\SearchSource\Provider\ProviderConfigurationException;
use App\Modules\SearchSource\Provider\ProviderRequestException;
use App\Modules\Tour\Entity\TourPackage;
use App\Modules\Tour\Enum\TourAvailabilityStatus;
use App\Modules\Tour\ValueObject\ExternalTourOfferCandidate;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchRequest;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchResult;
use App\Modules\Tour\ValueObject\TourMoney;

final readonly class FirecrawlExternalTourOfferProvider implements ExternalTourOfferProviderInterface
{
    public function __construct(private FirecrawlProvider $firecrawlProvider)
    {
    }

    public function supports(SearchSource $source): bool
    {
        return $this->firecrawlProvider->supports($source, SearchSource::CAPABILITY_TOUR);
    }

    public function search(SearchSource $source, ExternalTourOfferSearchRequest $request): ExternalTourOfferSearchResult
    {
        $metadata = [
            'provider' => $this->firecrawlProvider->getCode(),
            'sourceIdentifier' => ExternalTourOfferCandidate::sourceIdentifier($source),
            'requestContext' => $request->context(),
            'extractionMode' => 'structured_json',
            'strategy' => 'search_url_template',
            'accessStrategy' => 'firecrawl',
        ];

        $url = $this->requestUrl($source, $request);
        if ($url === null) {
            return ExternalTourOfferSearchResult::noData($source, $metadata + [
                'urlSelection' => 'none',
                'noDataReason' => 'SearchSource config must include tourSearchUrlTemplate or searchUrlTemplate.',
                'rawResultCount' => 0,
                'acceptedCandidateCount' => 0,
                'rejectedCandidateCount' => 0,
            ]);
        }

        $metadata['sourceUrlUsed'] = $url;
        $metadata['firecrawlRequest'] = [
            'endpoint' => '/v2/scrape',
            'url' => $url,
            'formatTypes' => ['markdown', 'json'],
        ];

        try {
            $response = $this->firecrawlProvider->request('POST', '/v2/scrape', $this->scrapePayload($url, $request));
        } catch (ProviderConfigurationException|ProviderRequestException $exception) {
            return ExternalTourOfferSearchResult::failure($source, [$exception->getMessage()], $metadata + ['exception' => $exception::class]);
        }

        if (($response['success'] ?? true) === false) {
            return ExternalTourOfferSearchResult::failure($source, [$this->apiError($response)], $metadata + ['topLevelKeys' => array_keys($response)]);
        }

        $offers = $this->offers($response);
        $markdownEvidence = $this->string($response['data']['markdown'] ?? null);
        if ($offers === null) {
            return ExternalTourOfferSearchResult::noData($source, $metadata + [
                'topLevelKeys' => array_keys($response),
                'noDataReason' => 'Firecrawl tour extraction response did not contain a usable structured JSON object.',
            ]);
        }

        if ($offers === []) {
            return ExternalTourOfferSearchResult::noResults($source, $metadata + [
                'topLevelKeys' => array_keys($response),
                'dataKeys' => \is_array($response['data'] ?? null) ? array_keys($response['data']) : [],
                'rawResultCount' => 0,
                'acceptedCandidateCount' => 0,
                'rejectedCandidateCount' => 0,
            ]);
        }

        $candidates = [];
        $diagnostics = [];
        foreach ($offers as $index => $offer) {
            [$candidate, $reasons] = $this->candidateFromOffer($source, $request, $offer, $markdownEvidence);
            if ($candidate instanceof ExternalTourOfferCandidate) {
                $candidates[$this->dedupeKey($candidate)] = $candidate;
                $diagnostics[] = [
                    'index' => $index,
                    'accepted' => true,
                    'normalized' => [
                        'title' => $candidate->title,
                        'destination' => $candidate->destinationText,
                        'hotelName' => $candidate->hotelName,
                        'currency' => $candidate->currency,
                        'totalPrice' => $candidate->totalPrice,
                    ],
                ];
                continue;
            }

            $diagnostics[] = [
                'index' => $index,
                'accepted' => false,
                'reasons' => $reasons !== [] ? $reasons : ['MALFORMED_EXTRACTION'],
            ];
        }

        $metadata += [
            'topLevelKeys' => array_keys($response),
            'dataKeys' => \is_array($response['data'] ?? null) ? array_keys($response['data']) : [],
            'markdownEvidenceLength' => $markdownEvidence !== null ? mb_strlen($markdownEvidence) : 0,
            'structuredExtraction' => ['offers' => $offers],
            'rawResultCount' => \count($offers),
            'acceptedCandidateCount' => \count($candidates),
            'rejectedCandidateCount' => \count(array_filter($diagnostics, static fn (array $diagnostic): bool => $diagnostic['accepted'] === false)),
            'offerDiagnostics' => $diagnostics,
        ];

        return $candidates !== []
            ? ExternalTourOfferSearchResult::success($source, array_values($candidates), $metadata)
            : ExternalTourOfferSearchResult::noData($source, $metadata + ['noDataReason' => 'No extracted tour offer passed strict acceptance.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function scrapePayload(string $url, ExternalTourOfferSearchRequest $request): array
    {
        return [
            'url' => $url,
            'formats' => ['markdown', [
                'type' => 'json',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'offers' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'externalOfferId' => ['type' => 'string'],
                                    'title' => ['type' => 'string'],
                                    'origin' => ['type' => 'string'],
                                    'destination' => ['type' => 'string'],
                                    'departureDate' => ['type' => 'string'],
                                    'returnDate' => ['type' => 'string'],
                                    'validFrom' => ['type' => 'string'],
                                    'validTo' => ['type' => 'string'],
                                    'nights' => ['type' => 'integer'],
                                    'days' => ['type' => 'integer'],
                                    'hotelName' => ['type' => 'string'],
                                    'roomName' => ['type' => 'string'],
                                    'boardType' => ['type' => 'string'],
                                    'flightSummary' => ['type' => 'string'],
                                    'adults' => ['type' => 'integer'],
                                    'children' => ['type' => 'integer'],
                                    'infants' => ['type' => 'integer'],
                                    'inclusions' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'exclusions' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'totalPrice' => ['type' => 'string'],
                                    'currency' => ['type' => 'string'],
                                    'bookingUrl' => ['type' => 'string'],
                                    'availabilityStatus' => ['type' => 'string'],
                                ],
                                'required' => ['title', 'destination', 'totalPrice', 'currency'],
                            ],
                        ],
                    ],
                    'required' => ['offers'],
                ],
                'prompt' => $this->extractionPrompt($request),
            ]],
        ];
    }

    private function extractionPrompt(ExternalTourOfferSearchRequest $request): string
    {
        return sprintf(
            'Extract only explicit commercial tour/package offers for destination %s, origin %s, departure %s, return %s, validity %s to %s, nights %s, %d adult(s), %d child(ren), %d infant(s). Return only offers with explicit title, destination, total price and currency. Include hotel, board, flight and inclusion facts only when explicit. Do not estimate prices, invent hotels, infer dates, or return unrelated offers.',
            $request->destinationCity->getName(),
            $request->originAirport?->getIataCode() ?? 'any',
            $request->departureDate?->format('Y-m-d') ?? 'none',
            $request->returnDate?->format('Y-m-d') ?? 'none',
            $request->validFrom?->format('Y-m-d') ?? 'none',
            $request->validTo?->format('Y-m-d') ?? 'none',
            $request->nights !== null ? (string) $request->nights : 'any',
            $request->adults,
            $request->children,
            $request->infants,
        );
    }

    private function requestUrl(SearchSource $source, ExternalTourOfferSearchRequest $request): ?string
    {
        $config = $source->getConfig();
        $template = $this->string($config['tourSearchUrlTemplate'] ?? $config['searchUrlTemplate'] ?? $config['urlTemplate'] ?? null);
        if ($template === null) {
            return null;
        }

        $originLocation = $this->locationSlug($config['originLocationMap'] ?? [], $request->originAirport?->getCity()?->getId(), $request->originAirport?->getCity()?->getSlug());
        $destinationLocation = $this->locationSlug($config['destinationLocationMap'] ?? [], $request->destinationCity->getId(), $request->destinationCity->getSlug());

        $url = strtr($template, [
            '{origin}' => $request->originAirport?->getIataCode() ?? '',
            '{originCity}' => $request->originAirport?->getCity()?->getSlug() ?? '',
            '{originLocation}' => $originLocation ?? '',
            '{destination}' => $request->destinationCity->getSlug() ?: $request->destinationCity->getName(),
            '{destinationLocation}' => $destinationLocation ?? '',
            '{destinationName}' => rawurlencode($request->destinationCity->getName()),
            '{departure}' => $request->departureDate?->format('Y-m-d') ?? '',
            '{return}' => $request->returnDate?->format('Y-m-d') ?? '',
            '{validFrom}' => $request->validFrom?->format('Y-m-d') ?? '',
            '{validTo}' => $request->validTo?->format('Y-m-d') ?? '',
            '{nights}' => $request->nights !== null ? (string) $request->nights : '',
            '{adults}' => (string) $request->adults,
            '{children}' => (string) $request->children,
            '{infants}' => (string) $request->infants,
            '{rooms}' => (string) $request->rooms,
            '{currency}' => $request->currency ?? '',
            '{budget}' => $request->budget !== null ? (TourMoney::normalize($request->budget) ?? '') : '',
        ]);

        $host = parse_url($url, PHP_URL_HOST);
        if (!\is_string($host) || !$this->domainMatches($host, $source->getDomain())) {
            return null;
        }

        return $url;
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function offers(array $response): ?array
    {
        $json = $response['data']['json'] ?? $response['json'] ?? $response['data']['extract'] ?? null;
        if (!\is_array($json)) {
            return null;
        }

        $offers = $json['offers'] ?? null;
        if (!\is_array($offers) || !array_is_list($offers)) {
            return null;
        }

        return array_values(array_filter($offers, static fn (mixed $offer): bool => \is_array($offer)));
    }

    /**
     * @param array<string, mixed> $offer
     *
     * @return array{0: ?ExternalTourOfferCandidate, 1: string[]}
     */
    private function candidateFromOffer(SearchSource $source, ExternalTourOfferSearchRequest $request, array $offer, ?string $markdownEvidence): array
    {
        $reasons = [];
        $title = $this->string($offer['title'] ?? null);
        $destination = $this->string($offer['destination'] ?? null);
        $currency = $this->currency($offer['currency'] ?? null);
        $totalPrice = TourMoney::normalize($offer['totalPrice'] ?? $offer['price'] ?? null);
        $departureDate = $this->date($offer['departureDate'] ?? null);
        $returnDate = $this->date($offer['returnDate'] ?? null);
        $validFrom = $this->date($offer['validFrom'] ?? null);
        $validTo = $this->date($offer['validTo'] ?? null);
        $nights = $this->integer($offer['nights'] ?? null);
        $adults = $this->integer($offer['adults'] ?? null) ?? $request->adults;
        $children = $this->integer($offer['children'] ?? null) ?? $request->children;
        $infants = $this->integer($offer['infants'] ?? null) ?? $request->infants;

        if ($title === null) {
            $reasons[] = 'MISSING_TITLE';
        }
        if ($destination === null) {
            $reasons[] = 'MISSING_DESTINATION';
        } elseif (!$this->destinationMatches($destination, $request)) {
            $reasons[] = 'DESTINATION_MISMATCH';
        }
        if ($currency === null) {
            $reasons[] = 'INVALID_CURRENCY';
        }
        if ($totalPrice === null) {
            $reasons[] = $this->ambiguousPrice($offer['totalPrice'] ?? $offer['price'] ?? null) ? 'AMBIGUOUS_PRICE' : 'MISSING_PRICE';
        }
        if ($adults !== $request->adults || $children !== $request->children || $infants !== $request->infants) {
            $reasons[] = 'PASSENGER_CONTEXT_MISMATCH';
        }
        if ($request->departureDate instanceof \DateTimeImmutable && $departureDate instanceof \DateTimeImmutable && $departureDate->format('Y-m-d') !== $request->departureDate->format('Y-m-d')) {
            $reasons[] = 'DATE_MISMATCH';
        }
        if ($request->returnDate instanceof \DateTimeImmutable && $returnDate instanceof \DateTimeImmutable && $returnDate->format('Y-m-d') !== $request->returnDate->format('Y-m-d')) {
            $reasons[] = 'DATE_MISMATCH';
        }
        if ($request->nights !== null && $nights !== null && $nights !== $request->nights) {
            $reasons[] = 'NIGHTS_MISMATCH';
        }
        if (!$departureDate instanceof \DateTimeImmutable && !$validFrom instanceof \DateTimeImmutable && $request->departureDate instanceof \DateTimeImmutable) {
            $reasons[] = 'MISSING_DATE_CONTEXT';
        }
        $reasons = array_merge($reasons, $this->sourceEvidenceReasons($offer, $markdownEvidence));

        if ($reasons !== []) {
            return [null, array_values(array_unique($reasons))];
        }

        try {
            return [new ExternalTourOfferCandidate(
                sourceIdentifier: ExternalTourOfferCandidate::sourceIdentifier($source),
                sourceName: $source->getName(),
                providerCode: $this->firecrawlProvider->getCode(),
                externalOfferId: $this->string($offer['externalOfferId'] ?? $offer['externalId'] ?? $offer['id'] ?? null),
                title: (string) $title,
                originText: $this->string($offer['origin'] ?? null),
                destinationText: (string) $destination,
                departureDate: $departureDate,
                returnDate: $returnDate,
                validFrom: $validFrom,
                validTo: $validTo,
                nights: $nights,
                days: $this->integer($offer['days'] ?? null),
                hotelName: $this->string($offer['hotelName'] ?? null),
                roomName: $this->string($offer['roomName'] ?? null),
                boardType: $this->boardType($offer['boardType'] ?? null),
                flightSummary: $this->string($offer['flightSummary'] ?? null),
                adults: $request->adults,
                children: $request->children,
                infants: $request->infants,
                childrenAges: $request->childrenAges,
                inclusions: $this->stringList($offer['inclusions'] ?? []),
                exclusions: $this->stringList($offer['exclusions'] ?? []),
                currency: (string) $currency,
                totalPrice: (string) $totalPrice,
                bookingUrl: $this->string($offer['bookingUrl'] ?? null),
                availabilityStatus: $this->availabilityStatus($offer['availabilityStatus'] ?? $offer['availability'] ?? null),
                metadata: ['rawProviderOffer' => $offer],
            ), []];
        } catch (\InvalidArgumentException $exception) {
            return [null, [$exception->getMessage()]];
        }
    }

    /**
     * @param array<string, mixed> $offer
     *
     * @return string[]
     */
    private function sourceEvidenceReasons(array $offer, ?string $markdownEvidence): array
    {
        if ($markdownEvidence === null || trim($markdownEvidence) === '') {
            return ['MISSING_SOURCE_EVIDENCE'];
        }

        $reasons = [];
        foreach (['title' => 'UNVERIFIED_TITLE', 'destination' => 'UNVERIFIED_DESTINATION', 'hotelName' => 'UNVERIFIED_HOTEL'] as $key => $reason) {
            $value = $this->string($offer[$key] ?? null);
            if ($value !== null && !$this->evidenceContains($markdownEvidence, $value)) {
                $reasons[] = $reason;
            }
        }

        $price = $this->string($offer['totalPrice'] ?? $offer['price'] ?? null);
        if ($price !== null && !$this->evidenceContainsPrice($markdownEvidence, $price)) {
            $reasons[] = 'UNVERIFIED_PRICE';
        }

        foreach (['departureDate' => 'UNVERIFIED_DEPARTURE_DATE', 'returnDate' => 'UNVERIFIED_RETURN_DATE', 'validFrom' => 'UNVERIFIED_VALID_FROM', 'validTo' => 'UNVERIFIED_VALID_TO'] as $key => $reason) {
            $date = $this->date($offer[$key] ?? null);
            if ($date instanceof \DateTimeImmutable && !$this->evidenceContains($markdownEvidence, $date->format('Y-m-d')) && !$this->evidenceContains($markdownEvidence, $date->format('d.m.Y'))) {
                $reasons[] = $reason;
            }
        }

        return array_values(array_unique($reasons));
    }

    private function destinationMatches(string $destination, ExternalTourOfferSearchRequest $request): bool
    {
        $haystack = $this->normalizedText($destination);
        $city = $this->normalizedText($request->destinationCity->getName());
        $slug = $this->normalizedText((string) $request->destinationCity->getSlug());

        return $city !== '' && str_contains($haystack, $city) || $slug !== '' && str_contains($haystack, $slug);
    }

    private function currency(mixed $value): ?string
    {
        $value = \is_string($value) ? strtoupper(trim($value)) : '';

        return preg_match('/^[A-Z]{3}$/', $value) === 1 ? $value : null;
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        if (!$value instanceof \DateTimeInterface && !\is_string($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Exception) {
            return null;
        }
    }

    private function integer(mixed $value): ?int
    {
        if (\is_int($value)) {
            return $value;
        }

        return \is_string($value) && preg_match('/^\d+$/', trim($value)) === 1 ? (int) trim($value) : null;
    }

    private function boardType(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $normalized = strtolower(str_replace(['-', '_'], ' ', trim($value)));

        return match (true) {
            str_contains($normalized, 'breakfast') => HotelBoardType::BREAKFAST->value,
            str_contains($normalized, 'half') => HotelBoardType::HALF_BOARD->value,
            str_contains($normalized, 'full') => HotelBoardType::FULL_BOARD->value,
            str_contains($normalized, 'all inclusive') => HotelBoardType::ALL_INCLUSIVE->value,
            str_contains($normalized, 'room only') => HotelBoardType::ROOM_ONLY->value,
            $normalized !== '' => HotelBoardType::OTHER->value,
            default => null,
        };
    }

    private function availabilityStatus(mixed $value): TourAvailabilityStatus
    {
        $value = \is_string($value) ? strtolower(trim(str_replace(['-', ' '], '_', $value))) : '';

        return TourAvailabilityStatus::tryFrom($value) ?? TourAvailabilityStatus::UNKNOWN;
    }

    /**
     * @return string[]
     */
    private function stringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return TourPackage::normalizeInclusionKeys(array_values(array_filter($value, 'is_scalar')));
    }

    private function string(mixed $value): ?string
    {
        $value = \is_scalar($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
    }

    private function ambiguousPrice(mixed $value): bool
    {
        return \is_string($value) && str_contains($value, ',');
    }

    private function evidenceContains(string $evidence, string $needle): bool
    {
        return mb_stripos($evidence, $needle) !== false;
    }

    private function evidenceContainsPrice(string $evidence, string $price): bool
    {
        if ($this->evidenceContains($evidence, trim($price))) {
            return true;
        }

        $normalized = TourMoney::normalize($price);
        if ($normalized === null) {
            return false;
        }

        $major = (string) ((int) explode('.', $normalized, 2)[0]);

        return preg_match('/(?:â‚¬|\\$|Â£)\\s*' . preg_quote($major, '/') . '(?:\\.\\d{1,2})?\\b/u', $evidence) === 1;
    }

    private function normalizedText(string $value): string
    {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', strtolower($value));
        $value = \is_string($converted) && $converted !== '' ? $converted : strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function domainMatches(string $hostOrDomain, string $domain): bool
    {
        $host = strtolower(preg_replace('/^www\./', '', trim($hostOrDomain)) ?? $hostOrDomain);
        $host = explode('/', $host)[0];
        $domain = strtolower(preg_replace('/^www\./', '', trim($domain)) ?? $domain);

        return $host === $domain || str_ends_with($host, '.' . $domain);
    }

    private function dedupeKey(ExternalTourOfferCandidate $candidate): string
    {
        return $candidate->externalOfferId ?? implode('|', [$candidate->title, $candidate->currency, $candidate->totalPrice, $candidate->bookingUrl ?? '']);
    }

    /**
     * @param mixed $map
     */
    private function locationSlug(mixed $map, ?int $cityId, ?string $fallback): ?string
    {
        if (\is_array($map) && $cityId !== null) {
            $mapped = $this->string($map[(string) $cityId] ?? $map[$cityId] ?? null);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function apiError(array $response): string
    {
        foreach (['error', 'message'] as $key) {
            $value = $this->string($response[$key] ?? null);
            if ($value !== null) {
                return 'Firecrawl API error: ' . $value;
            }
        }

        return 'Firecrawl API returned an unsuccessful response.';
    }
}
