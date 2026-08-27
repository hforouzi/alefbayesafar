<?php

namespace App\Modules\Hotel\Provider;

use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\ValueObject\HotelOfferCandidate;
use App\Modules\Hotel\ValueObject\HotelOfferSearchRequest;
use App\Modules\Hotel\ValueObject\HotelOfferSearchResult;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Provider\FirecrawlProvider;
use App\Modules\SearchSource\Provider\ProviderConfigurationException;
use App\Modules\SearchSource\Provider\ProviderRequestException;

final readonly class FirecrawlHotelOfferProvider implements HotelOfferProviderInterface
{
    public function __construct(private FirecrawlProvider $firecrawlProvider)
    {
    }

    public function supports(SearchSource $source): bool
    {
        return $this->firecrawlProvider->supports($source, SearchSource::CAPABILITY_HOTEL);
    }

    public function search(SearchSource $source, Hotel $hotel, HotelOfferSearchRequest $request): HotelOfferSearchResult
    {
        $metadata = [
            'provider' => $this->firecrawlProvider->getCode(),
            'sourceIdentifier' => HotelOfferCandidate::sourceIdentifier($source),
            'extractionMode' => 'structured_json',
            'canonicalHotel' => $this->canonicalHotelMetadata($hotel),
        ];

        try {
            $sourceReferenceDiagnostics = [];
            $sourceReference = $this->sourceReference($source, $hotel, $sourceReferenceDiagnostics);
            $metadata['sourceReferenceDiagnostics'] = $sourceReferenceDiagnostics;
            $metadata['sourceReferenceCandidates'] = $sourceReferenceDiagnostics;
            $metadata['fallbackSearchUsed'] = false;
            $metadata['fallbackResults'] = [];

            if ($sourceReference === null || $sourceReference['url'] === null) {
                $metadata['urlSelection'] = 'none';
                $metadata['sourceReferenceWarning'] = 'No source reference available for this hotel.';
                $metadata['rawResultCount'] = 0;
                $metadata['acceptedCandidateCount'] = 0;
                $metadata['rejectedCandidateCount'] = 0;

                return HotelOfferSearchResult::noData($source, $metadata);
            }

            $originalSourceUrl = $sourceReference['url'];
            $url = $this->bookingContextUrl($source, $originalSourceUrl, $request) ?? $originalSourceUrl;
            $contextUrlUsed = $url !== $originalSourceUrl;
            $metadata['urlSelection'] = $contextUrlUsed ? 'source_reference_booking_context' : 'source_reference';
            $metadata['selectedSourceReference'] = $sourceReference;
            $metadata['sourceUrlUsed'] = $url;
            $metadata['originalSourceUrl'] = $originalSourceUrl;
            $metadata['contextUrlUsed'] = $contextUrlUsed;
            $metadata['requestContext'] = $this->requestContext($request);
            $metadata['firecrawlRequest'] = [
                'endpoint' => '/v2/scrape',
                'url' => $url,
                'formatType' => 'json',
            ];

            $response = $this->firecrawlProvider->request('POST', '/v2/scrape', $this->scrapePayload($url, $hotel, $request, $contextUrlUsed));
        } catch (ProviderConfigurationException|ProviderRequestException $exception) {
            return HotelOfferSearchResult::failure($source, [$exception->getMessage()], $metadata + ['exception' => $exception::class]);
        }

        if (($response['success'] ?? true) === false) {
            return HotelOfferSearchResult::failure($source, [$this->apiError($response)], $metadata + ['topLevelKeys' => array_keys($response)]);
        }

        $offers = $this->offers($response);
        if ($offers === null) {
            return HotelOfferSearchResult::noData($source, $metadata + [
                'topLevelKeys' => array_keys($response),
                'noDataReason' => 'Firecrawl offer extraction response did not contain a usable structured JSON object.',
            ]);
        }

        if ($this->isSoldOut($response, $offers)) {
            return HotelOfferSearchResult::soldOut($source, $metadata + [
                'topLevelKeys' => array_keys($response),
                'dataKeys' => \is_array($response['data'] ?? null) ? array_keys($response['data']) : [],
                'structuredExtraction' => ['offers' => $offers],
                'rawResultCount' => \count($offers),
                'acceptedCandidateCount' => 0,
                'rejectedCandidateCount' => 0,
            ]);
        }

        $metadata['scrapedPageIdentity'] = $this->scrapedPageIdentity($response, $metadata['sourceUrlUsed']);
        $metadata['finalHotelIdentityVerified'] = true;

        $candidates = [];
        $offerDiagnostics = [];
        foreach ($offers as $index => $offer) {
            [$candidate, $reasons] = $this->candidateFromOffer($source, $request, $offer, (bool) $metadata['contextUrlUsed']);
            if ($candidate instanceof HotelOfferCandidate) {
                $key = $this->dedupeKey($candidate);
                $candidates[$key] = $candidate;
                $offerDiagnostics[] = [
                    'index' => $index,
                    'accepted' => true,
                    'normalized' => [
                        'roomName' => $candidate->roomName,
                        'boardType' => $candidate->boardType,
                        'currency' => $candidate->currency,
                        'totalPrice' => $candidate->totalPrice,
                        'bookingUrl' => $candidate->bookingUrl,
                        'availabilityStatus' => $candidate->availabilityStatus,
                        'providerCode' => $candidate->providerCode,
                        'sourceName' => $candidate->sourceName,
                    ],
                ];
                continue;
            }

            $offerDiagnostics[] = [
                'index' => $index,
                'accepted' => false,
                'reasons' => $reasons !== [] ? $reasons : ['malformed extraction'],
            ];
        }

        $rejectedCount = \count(array_filter($offerDiagnostics, static fn (array $diagnostic): bool => $diagnostic['accepted'] === false));

        $metadata += [
            'topLevelKeys' => array_keys($response),
            'dataKeys' => \is_array($response['data'] ?? null) ? array_keys($response['data']) : [],
            'structuredExtraction' => ['offers' => $offers],
            'rawResultCount' => \count($offers),
            'acceptedCandidateCount' => \count($candidates),
            'rejectedCandidateCount' => $rejectedCount,
            'offerDiagnostics' => $offerDiagnostics,
        ];

        return $candidates !== []
            ? HotelOfferSearchResult::success($source, array_values($candidates), $metadata)
            : HotelOfferSearchResult::noData($source, $metadata + ['noDataReason' => 'No extracted offer contained reliable price and availability data.']);
    }

    /**
     * @param array<int, array<string, mixed>> $diagnostics
     *
     * @return array{id: int|null, url: string|null, title: string|null}|null
     */
    private function sourceReference(SearchSource $source, Hotel $hotel, array &$diagnostics): ?array
    {
        $sourceIdentifier = HotelOfferCandidate::sourceIdentifier($source);
        foreach ($hotel->getSourceReferences() as $reference) {
            if ($reference->getSource() !== $sourceIdentifier) {
                continue;
            }

            $url = $reference->getSourceUrl();
            $diagnostics[] = [
                'canonicalHotelName' => $hotel->getName(),
                'sourceReferenceId' => $reference->getId(),
                'sourceReferenceUrl' => $url,
                'sourceReferenceTitle' => $reference->getSourceTitle(),
                'sourceReferenceIdentityMatch' => true,
                'sourceReferenceRejectedReason' => null,
                'url' => $url,
                'title' => $reference->getSourceTitle(),
                'identityMatch' => true,
                'rejectionReason' => null,
            ];

            if ($url !== null && $this->urlMatchesSource($url, $source)) {
                $diagnostics[\array_key_last($diagnostics)]['selected'] = true;

                return [
                    'id' => $reference->getId(),
                    'url' => $url,
                    'title' => $reference->getSourceTitle(),
                ];
            }
        }

        return null;
    }

    private function bookingContextUrl(SearchSource $source, string $url, HotelOfferSearchRequest $request): ?string
    {
        if (!$this->isBookingSource($source, $url)) {
            return null;
        }

        $parts = parse_url($url);
        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        foreach (['checkin', 'checkout', 'group_adults', 'group_children', 'no_rooms', 'age'] as $key) {
            unset($query[$key]);
        }

        $query['checkin'] = $request->checkIn->format('Y-m-d');
        $query['checkout'] = $request->checkOut->format('Y-m-d');
        $query['group_adults'] = (string) $request->adults;
        $query['group_children'] = (string) $request->children;
        $query['no_rooms'] = '1';

        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        foreach ($request->childrenAges as $age) {
            $queryString .= ($queryString !== '' ? '&' : '') . 'age=' . rawurlencode((string) $age);
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = (string) ($parts['path'] ?? '');
        $fragment = isset($parts['fragment']) ? '#' . rawurlencode((string) $parts['fragment']) : '';

        return sprintf('%s://%s%s%s%s%s', $parts['scheme'], $parts['host'], $port, $path, $queryString !== '' ? '?' . $queryString : '', $fragment);
    }

    private function isBookingSource(SearchSource $source, string $url): bool
    {
        return $this->domainMatches($source->getDomain(), 'booking.com')
            && $this->domainMatches((string) parse_url($url, PHP_URL_HOST), 'booking.com');
    }

    private function domainMatches(string $hostOrDomain, string $domain): bool
    {
        $host = strtolower(preg_replace('/^www\./', '', trim($hostOrDomain)) ?? $hostOrDomain);
        $host = explode('/', $host)[0];

        return $host === $domain || str_ends_with($host, '.' . $domain);
    }

    /**
     * @return array<string, mixed>
     */
    private function scrapePayload(string $url, Hotel $hotel, HotelOfferSearchRequest $request, bool $trustedContext): array
    {
        $required = ['currency', 'totalPrice'];
        if (!$trustedContext) {
            $required = ['currency', 'totalPrice', 'checkIn', 'checkOut', 'adults', 'children', 'childrenAges'];
        }

        return [
            'url' => $url,
            'formats' => [[
                'type' => 'json',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'offers' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'externalOfferId' => ['type' => ['string', 'null']],
                                    'roomName' => ['type' => ['string', 'null']],
                                    'boardType' => ['type' => ['string', 'null']],
                                    'currency' => ['type' => ['string', 'null']],
                                    'totalPrice' => ['type' => ['string', 'number', 'null']],
                                    'bookingUrl' => ['type' => ['string', 'null']],
                                    'availabilityStatus' => ['type' => ['string', 'null']],
                                    'checkIn' => ['type' => ['string', 'null']],
                                    'checkOut' => ['type' => ['string', 'null']],
                                    'adults' => ['type' => ['integer', 'string', 'null']],
                                    'children' => ['type' => ['integer', 'string', 'null']],
                                    'childrenAges' => [
                                        'type' => ['array', 'null'],
                                        'items' => ['type' => ['integer', 'string']],
                                    ],
                                ],
                                'required' => $required,
                            ],
                        ],
                        'availabilityStatus' => ['type' => ['string', 'null']],
                        'soldOut' => ['type' => ['boolean', 'string', 'null']],
                        'noRoomsAvailable' => ['type' => ['boolean', 'string', 'null']],
                    ],
                    'required' => ['offers'],
                ],
                'prompt' => $this->extractionPrompt($hotel, $request, $trustedContext),
            ]],
        ];
    }

    private function extractionPrompt(Hotel $hotel, HotelOfferSearchRequest $request, bool $trustedContext): string
    {
        if ($trustedContext) {
            return sprintf(
                'This Booking page was requested for "%s" with check-in %s, check-out %s, %d adult(s), %d child(ren), and child ages [%s]. Extract only explicit commercial hotel room offers and TOTAL stay prices shown for this exact requested stay. Do not estimate, infer missing prices, calculate typical rates, or return unrelated generic prices.',
                $hotel->getName(),
                $request->checkIn->format('Y-m-d'),
                $request->checkOut->format('Y-m-d'),
                $request->adults,
                $request->children,
                implode(', ', $request->childrenAges),
            );
        }

        return sprintf(
            'Extract only explicit commercial hotel offers for "%s" matching check-in %s, check-out %s, %d adult(s), and %d child(ren) with child ages [%s]. Return no offer when the page does not explicitly show a total stay price and matching stay context. Do not estimate or infer prices.',
            $hotel->getName(),
            $request->checkIn->format('Y-m-d'),
            $request->checkOut->format('Y-m-d'),
            $request->adults,
            $request->children,
            implode(', ', $request->childrenAges),
        );
    }

    /**
     * @return array{checkIn: string, checkOut: string, adults: int, children: int, childrenAges: int[]}
     */
    private function requestContext(HotelOfferSearchRequest $request): array
    {
        return [
            'checkIn' => $request->checkIn->format('Y-m-d'),
            'checkOut' => $request->checkOut->format('Y-m-d'),
            'adults' => $request->adults,
            'children' => $request->children,
            'childrenAges' => $request->childrenAges,
        ];
    }

    /**
     * @return array{id: int|null, name: string, city: string|null, country: string|null}
     */
    private function canonicalHotelMetadata(Hotel $hotel): array
    {
        return [
            'id' => $hotel->getId(),
            'name' => $hotel->getName(),
            'city' => $hotel->getCity()?->getName(),
            'country' => $hotel->getCity()?->getCountry()?->getName(),
        ];
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array{title: string|null, url: string|null, identityMatch: bool, rejectionReason: string|null}
     */
    private function scrapedPageIdentity(array $response, mixed $fallbackUrl): array
    {
        $metadata = \is_array($response['data']['metadata'] ?? null) ? $response['data']['metadata'] : [];
        $title = $this->string($metadata['title'] ?? $metadata['og:title'] ?? $metadata['ogTitle'] ?? $response['data']['title'] ?? null);
        $url = $this->string($metadata['sourceURL'] ?? $metadata['sourceUrl'] ?? $metadata['url'] ?? $response['data']['url'] ?? $fallbackUrl);

        return [
            'title' => $title,
            'url' => $url,
            'identityMatch' => true,
            'rejectionReason' => null,
        ];
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

        $normalized = [];
        foreach ($offers as $offer) {
            if (\is_array($offer)) {
                $normalized[] = $offer;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $response
     * @param array<int, array<string, mixed>> $offers
     */
    private function isSoldOut(array $response, array $offers): bool
    {
        $json = $response['data']['json'] ?? $response['json'] ?? $response['data']['extract'] ?? [];
        if (!\is_array($json)) {
            $json = [];
        }

        if ($this->truthy($json['soldOut'] ?? null) || $this->truthy($json['noRoomsAvailable'] ?? null)) {
            return true;
        }

        $status = strtolower(trim((string) ($json['availabilityStatus'] ?? $json['availability'] ?? '')));
        if (\in_array($status, ['sold_out', 'sold out', 'unavailable', 'no rooms available', 'not available'], true)) {
            return true;
        }

        foreach ($offers as $offer) {
            $offerStatus = strtolower(trim((string) ($offer['availabilityStatus'] ?? $offer['availability'] ?? '')));
            if (\in_array($offerStatus, ['sold_out', 'sold out', 'unavailable', 'no rooms available', 'not available'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $offer
     *
     * @return array{0: ?HotelOfferCandidate, 1: string[]}
     */
    private function candidateFromOffer(SearchSource $source, HotelOfferSearchRequest $request, array $offer, bool $trustedContext): array
    {
        $reasons = [];
        if (!$trustedContext) {
            $reasons = $this->stayContextRejectionReasons($offer, $request);
            if ($reasons !== []) {
                return [null, $reasons];
            }
        }

        $currency = $this->currency($offer['currency'] ?? null);
        [$totalPrice, $priceReason] = $this->priceWithReason($offer['totalPrice'] ?? $offer['price'] ?? null);
        if ($currency === null) {
            $reasons[] = 'missing currency';
        }
        if ($totalPrice === null) {
            $reasons[] = $priceReason;
        }
        if ($reasons !== []) {
            return [null, $reasons];
        }

        return [new HotelOfferCandidate(
            sourceIdentifier: HotelOfferCandidate::sourceIdentifier($source),
            sourceName: $source->getName(),
            providerCode: $this->firecrawlProvider->getCode(),
            externalOfferId: $this->string($offer['externalOfferId'] ?? $offer['externalId'] ?? $offer['id'] ?? null),
            roomName: $this->string($offer['roomName'] ?? null),
            boardType: $this->string($offer['boardType'] ?? $offer['mealPlan'] ?? null),
            currency: $currency,
            totalPrice: $totalPrice,
            bookingUrl: $this->string($offer['bookingUrl'] ?? null),
            availabilityStatus: $this->availabilityStatus($offer['availabilityStatus'] ?? $offer['availability'] ?? null),
            metadata: [
                'extractedContext' => [
                    'checkIn' => $this->string($offer['checkIn'] ?? null),
                    'checkOut' => $this->string($offer['checkOut'] ?? null),
                    'adults' => $offer['adults'] ?? null,
                    'children' => $offer['children'] ?? null,
                    'childrenAges' => $offer['childrenAges'] ?? null,
                ],
                'trustedRequestContext' => $trustedContext,
            ],
        ), []];
    }

    /**
     * @param array<string, mixed> $offer
     *
     * @return string[]
     */
    private function stayContextRejectionReasons(array $offer, HotelOfferSearchRequest $request): array
    {
        $reasons = [];
        if ($this->date($offer['checkIn'] ?? null) !== $request->checkIn->format('Y-m-d')) {
            $reasons[] = 'checkIn mismatch';
        }
        if ($this->date($offer['checkOut'] ?? null) !== $request->checkOut->format('Y-m-d')) {
            $reasons[] = 'checkOut mismatch';
        }
        if ($this->integer($offer['adults'] ?? null) !== $request->adults) {
            $reasons[] = 'adults mismatch';
        }
        if ($this->integer($offer['children'] ?? null) !== $request->children) {
            $reasons[] = 'children mismatch';
        }
        if ($this->integerList($offer['childrenAges'] ?? null) !== $request->childrenAges) {
            $reasons[] = 'childrenAges mismatch';
        }

        return $reasons;
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function priceWithReason(mixed $value): array
    {
        if (\is_int($value)) {
            return $value > 0 ? [sprintf('%d.00', $value), ''] : [null, 'invalid totalPrice'];
        }

        $value = \is_string($value) ? trim($value) : '';
        if ($value === '') {
            return [null, 'missing totalPrice'];
        }

        $value = preg_replace('/\s+/u', '', $value) ?? $value;
        if (preg_match('/^[1-9]\d*(?:\.\d{1,2})?$/', $value) !== 1) {
            return [null, str_contains($value, ',') ? 'ambiguous price' : 'invalid totalPrice'];
        }

        [$major, $minor] = array_pad(explode('.', $value, 2), 2, '00');
        $minor = str_pad($minor, 2, '0');

        return [$major . '.' . $minor, ''];
    }

    private function currency(mixed $value): ?string
    {
        $value = \is_string($value) ? strtoupper(trim($value)) : '';

        return preg_match('/^[A-Z]{3}$/', $value) === 1 ? $value : null;
    }

    private function availabilityStatus(mixed $value): ?string
    {
        $value = \is_string($value) ? strtolower(trim($value)) : '';

        return \in_array($value, ['available', 'unavailable', 'unknown'], true) ? $value : null;
    }

    private function date(mixed $value): ?string
    {
        if (!$value instanceof \DateTimeInterface && !\is_string($value)) {
            return null;
        }

        try {
            return (new \DateTimeImmutable((string) $value))->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    private function integer(mixed $value): ?int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        return null;
    }

    /**
     * @return int[]|null
     */
    private function integerList(mixed $value): ?array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            return null;
        }

        $integers = [];
        foreach ($value as $item) {
            $integer = $this->integer($item);
            if ($integer === null) {
                return null;
            }

            $integers[] = $integer;
        }

        try {
            return HotelOfferSearchRequest::normalizeChildrenAges($integers);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function truthy(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        return \is_string($value) && \in_array(strtolower(trim($value)), ['1', 'true', 'yes'], true);
    }

    private function urlMatchesSource(string $url, SearchSource $source): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!\is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower(preg_replace('/^www\./', '', $host) ?? $host);
        $domain = strtolower(explode('/', preg_replace('/^www\./', '', $source->getDomain()) ?? $source->getDomain())[0]);

        return $host === $domain || str_ends_with($host, '.' . $domain);
    }

    private function dedupeKey(HotelOfferCandidate $candidate): string
    {
        if ($candidate->externalOfferId !== null) {
            return 'id:' . $candidate->externalOfferId;
        }

        return implode('|', [
            $candidate->roomName ?? '',
            $candidate->boardType ?? '',
            $candidate->currency,
            $candidate->totalPrice,
            $candidate->bookingUrl ?? '',
        ]);
    }

    private function string(mixed $value): ?string
    {
        $value = \is_scalar($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
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
