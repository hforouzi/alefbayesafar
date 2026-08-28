<?php

namespace App\Modules\Flight\Provider;

use App\Modules\Destination\Repository\AirportRepository;
use App\Modules\Flight\Enum\FlightAvailabilityStatus;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\Flight\Enum\FlightDirection;
use App\Modules\Flight\Enum\FlightTripType;
use App\Modules\Flight\ValueObject\FlightMoney;
use App\Modules\Flight\ValueObject\FlightOfferCandidate;
use App\Modules\Flight\ValueObject\FlightOfferLegCandidate;
use App\Modules\Flight\ValueObject\FlightOfferSearchRequest;
use App\Modules\Flight\ValueObject\FlightOfferSearchResult;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Provider\FirecrawlProvider;
use App\Modules\SearchSource\Provider\ProviderConfigurationException;
use App\Modules\SearchSource\Provider\ProviderRequestException;

final readonly class FirecrawlFlightOfferProvider implements FlightOfferProviderInterface
{
    public function __construct(
        private FirecrawlProvider $firecrawlProvider,
        private AirportRepository $airportRepository,
    ) {
    }

    public function supports(SearchSource $source): bool
    {
        return $this->firecrawlProvider->supports($source, SearchSource::CAPABILITY_FLIGHT);
    }

    public function search(SearchSource $source, FlightOfferSearchRequest $request): FlightOfferSearchResult
    {
        $metadata = [
            'provider' => $this->firecrawlProvider->getCode(),
            'sourceIdentifier' => FlightOfferCandidate::sourceIdentifier($source),
            'requestContext' => $request->context(),
            'extractionMode' => 'structured_json',
            'strategy' => 'search_url_template',
        ];

        $url = $this->requestUrl($source, $request);
        if ($url === null) {
            return FlightOfferSearchResult::noData($source, $metadata + [
                'urlSelection' => 'none',
                'noDataReason' => 'SearchSource config must include flightSearchUrlTemplate or searchUrlTemplate.',
                'rawResultCount' => 0,
                'acceptedCandidateCount' => 0,
                'rejectedCandidateCount' => 0,
            ]);
        }

        $metadata['sourceUrlUsed'] = $url;
        $metadata['firecrawlRequest'] = [
            'endpoint' => '/v2/scrape',
            'url' => $url,
            'formatType' => 'json',
        ];

        try {
            $response = $this->firecrawlProvider->request('POST', '/v2/scrape', $this->scrapePayload($url, $request));
        } catch (ProviderConfigurationException|ProviderRequestException $exception) {
            return FlightOfferSearchResult::failure($source, [$exception->getMessage()], $metadata + ['exception' => $exception::class]);
        }

        if (($response['success'] ?? true) === false) {
            return FlightOfferSearchResult::failure($source, [$this->apiError($response)], $metadata + ['topLevelKeys' => array_keys($response)]);
        }

        $offers = $this->offers($response);
        if ($offers === null) {
            return FlightOfferSearchResult::noData($source, $metadata + [
                'topLevelKeys' => array_keys($response),
                'noDataReason' => 'Firecrawl flight extraction response did not contain a usable structured JSON object.',
            ]);
        }

        if ($offers === []) {
            return FlightOfferSearchResult::noResults($source, $metadata + [
                'topLevelKeys' => array_keys($response),
                'dataKeys' => \is_array($response['data'] ?? null) ? array_keys($response['data']) : [],
                'rawResultCount' => 0,
                'acceptedCandidateCount' => 0,
                'rejectedCandidateCount' => 0,
            ]);
        }

        $candidates = [];
        $offerDiagnostics = [];
        foreach ($offers as $index => $offer) {
            [$candidate, $reasons] = $this->candidateFromOffer($source, $request, $offer);
            if ($candidate instanceof FlightOfferCandidate) {
                $candidates[$this->dedupeKey($candidate)] = $candidate;
                $offerDiagnostics[] = [
                    'index' => $index,
                    'accepted' => true,
                    'normalized' => [
                        'externalOfferId' => $candidate->externalOfferId,
                        'tripType' => $candidate->tripType->value,
                        'currency' => $candidate->currency,
                        'totalPrice' => $candidate->totalPrice,
                        'outboundLegs' => \count($candidate->outboundLegs),
                        'inboundLegs' => \count($candidate->inboundLegs),
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
            ? FlightOfferSearchResult::success($source, array_values($candidates), $metadata)
            : FlightOfferSearchResult::noData($source, $metadata + ['noDataReason' => 'No extracted flight offer passed strict acceptance.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function scrapePayload(string $url, FlightOfferSearchRequest $request): array
    {
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
                                    'tripType' => ['type' => ['string', 'null']],
                                    'cabinClass' => ['type' => ['string', 'null']],
                                    'totalPrice' => ['type' => ['string', 'number', 'null']],
                                    'currency' => ['type' => ['string', 'null']],
                                    'baggage' => ['type' => ['string', 'null']],
                                    'bookingUrl' => ['type' => ['string', 'null']],
                                    'availabilityStatus' => ['type' => ['string', 'null']],
                                    'adults' => ['type' => ['integer', 'string', 'null']],
                                    'children' => ['type' => ['integer', 'string', 'null']],
                                    'infants' => ['type' => ['integer', 'string', 'null']],
                                    'outbound' => ['type' => 'array', 'items' => $this->legSchema()],
                                    'inbound' => ['type' => ['array', 'null'], 'items' => $this->legSchema()],
                                ],
                                'required' => ['totalPrice', 'currency', 'outbound'],
                            ],
                        ],
                    ],
                    'required' => ['offers'],
                ],
                'prompt' => $this->extractionPrompt($request),
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function legSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'airlineName' => ['type' => ['string', 'null']],
                'airlineIata' => ['type' => ['string', 'null']],
                'airlineIcao' => ['type' => ['string', 'null']],
                'flightNumber' => ['type' => ['string', 'null']],
                'originIata' => ['type' => ['string', 'null']],
                'destinationIata' => ['type' => ['string', 'null']],
                'departureAt' => ['type' => ['string', 'null']],
                'arrivalAt' => ['type' => ['string', 'null']],
                'durationMinutes' => ['type' => ['integer', 'string', 'null']],
                'aircraft' => ['type' => ['string', 'null']],
            ],
            'required' => ['originIata', 'destinationIata', 'departureAt', 'arrivalAt'],
        ];
    }

    private function extractionPrompt(FlightOfferSearchRequest $request): string
    {
        return sprintf(
            'Extract only explicit commercial flight offers matching %s to %s, departure %s, return %s, %d adult(s), %d child(ren), %d infant(s), cabin %s. Return only offers with explicit total price, currency, airport IATA codes, and leg departure/arrival timestamps. Do not estimate prices, infer missing airport codes, invent flight times, or return unrelated offers.',
            $request->originAirport->getIataCode(),
            $request->destinationAirport->getIataCode(),
            $request->departureDate->format('Y-m-d'),
            $request->returnDate?->format('Y-m-d') ?? 'none',
            $request->adults,
            $request->children,
            $request->infants,
            $request->cabinClass->value,
        );
    }

    private function requestUrl(SearchSource $source, FlightOfferSearchRequest $request): ?string
    {
        $config = $source->getConfig();
        $template = $this->string($config['flightSearchUrlTemplate'] ?? $config['searchUrlTemplate'] ?? $config['urlTemplate'] ?? null);
        if ($template === null) {
            return null;
        }

        $replacements = [
            '{origin}' => (string) $request->originAirport->getIataCode(),
            '{destination}' => (string) $request->destinationAirport->getIataCode(),
            '{departure}' => $request->departureDate->format('Y-m-d'),
            '{return}' => $request->returnDate?->format('Y-m-d') ?? '',
            '{adults}' => (string) $request->adults,
            '{children}' => (string) $request->children,
            '{infants}' => (string) $request->infants,
            '{cabin}' => $request->cabinClass->value,
            '{tripType}' => $request->tripType->value,
            '{directOnly}' => $request->directOnly === null ? '' : ($request->directOnly ? '1' : '0'),
        ];

        $url = strtr($template, $replacements);
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
     * @return array{0: ?FlightOfferCandidate, 1: string[]}
     */
    private function candidateFromOffer(SearchSource $source, FlightOfferSearchRequest $request, array $offer): array
    {
        $reasons = [];
        $currency = $this->currency($offer['currency'] ?? null);
        $totalPrice = FlightMoney::normalize($offer['totalPrice'] ?? $offer['price'] ?? null);
        $tripType = $this->tripType($offer['tripType'] ?? null) ?? $request->tripType;
        $cabinClass = $this->cabinClass($offer['cabinClass'] ?? null) ?? $request->cabinClass;
        $adults = $this->integer($offer['adults'] ?? null) ?? $request->adults;
        $children = $this->integer($offer['children'] ?? null) ?? $request->children;
        $infants = $this->integer($offer['infants'] ?? null) ?? $request->infants;

        if ($currency === null) {
            $reasons[] = 'missing currency';
        }
        if ($totalPrice === null) {
            $reasons[] = $this->ambiguousPrice($offer['totalPrice'] ?? $offer['price'] ?? null) ? 'ambiguous price' : 'missing or invalid totalPrice';
        }
        if ($tripType !== $request->tripType) {
            $reasons[] = 'trip type mismatch';
        }
        if ($cabinClass !== $request->cabinClass) {
            $reasons[] = 'cabin class mismatch';
        }
        if ($adults !== $request->adults || $children !== $request->children || $infants !== $request->infants) {
            $reasons[] = 'passenger context mismatch';
        }

        [$outboundLegs, $outboundReasons] = $this->legs($offer['outbound'] ?? null, FlightDirection::OUTBOUND, $request);
        [$inboundLegs, $inboundReasons] = $this->legs($offer['inbound'] ?? null, FlightDirection::INBOUND, $request);
        $reasons = array_merge($reasons, $outboundReasons);
        if ($request->tripType === FlightTripType::ROUND_TRIP) {
            $reasons = array_merge($reasons, $inboundReasons);
            if ($inboundLegs === []) {
                $reasons[] = 'round trip missing usable inbound legs';
            }
        }

        if ($outboundLegs === []) {
            $reasons[] = 'missing usable outbound legs';
        }

        if ($outboundLegs !== []) {
            $first = $outboundLegs[0];
            $last = $outboundLegs[array_key_last($outboundLegs)];
            if ($first->originIata !== $request->originAirport->getIataCode() || $last->destinationIata !== $request->destinationAirport->getIataCode()) {
                $reasons[] = 'outbound route mismatch';
            }
            if ($first->departureAt->format('Y-m-d') !== $request->departureDate->format('Y-m-d')) {
                $reasons[] = 'departure date mismatch';
            }
            if ($request->directOnly === true && \count($outboundLegs) > 1) {
                $reasons[] = 'direct only rejected connection';
            }
        }

        if ($request->tripType === FlightTripType::ROUND_TRIP && $inboundLegs !== []) {
            $first = $inboundLegs[0];
            $last = $inboundLegs[array_key_last($inboundLegs)];
            if ($first->originIata !== $request->destinationAirport->getIataCode() || $last->destinationIata !== $request->originAirport->getIataCode()) {
                $reasons[] = 'inbound route mismatch';
            }
            if ($request->returnDate instanceof \DateTimeImmutable && $first->departureAt->format('Y-m-d') !== $request->returnDate->format('Y-m-d')) {
                $reasons[] = 'return date mismatch';
            }
            if ($request->directOnly === true && \count($inboundLegs) > 1) {
                $reasons[] = 'direct only rejected inbound connection';
            }
        }

        if ($reasons !== []) {
            return [null, array_values(array_unique($reasons))];
        }

        return [new FlightOfferCandidate(
            sourceIdentifier: FlightOfferCandidate::sourceIdentifier($source),
            sourceName: $source->getName(),
            providerCode: $this->firecrawlProvider->getCode(),
            externalOfferId: $this->string($offer['externalOfferId'] ?? $offer['externalId'] ?? $offer['id'] ?? null),
            tripType: $request->tripType,
            adults: $request->adults,
            children: $request->children,
            infants: $request->infants,
            cabinClass: $request->cabinClass,
            currency: (string) $currency,
            totalPrice: (string) $totalPrice,
            baggage: $this->string($offer['baggage'] ?? null),
            bookingUrl: $this->string($offer['bookingUrl'] ?? null),
            availabilityStatus: $this->availabilityStatus($offer['availabilityStatus'] ?? $offer['availability'] ?? null),
            outboundLegs: $outboundLegs,
            inboundLegs: $request->tripType === FlightTripType::ROUND_TRIP ? $inboundLegs : [],
            metadata: [
                'rawProviderOffer' => $offer,
            ],
        ), []];
    }

    /**
     * @return array{0: FlightOfferLegCandidate[], 1: string[]}
     */
    private function legs(mixed $items, FlightDirection $direction, FlightOfferSearchRequest $request): array
    {
        if (!\is_array($items) || !array_is_list($items)) {
            return [[], [$direction->value . ' legs missing']];
        }

        $legs = [];
        $reasons = [];
        foreach ($items as $index => $item) {
            if (!\is_array($item)) {
                $reasons[] = $direction->value . ' leg ' . $index . ' malformed';
                continue;
            }

            [$leg, $legReasons] = $this->leg($item, $direction, $index);
            if ($leg instanceof FlightOfferLegCandidate) {
                $legs[] = $leg;
                continue;
            }

            foreach ($legReasons as $reason) {
                $reasons[] = $direction->value . ' leg ' . $index . ': ' . $reason;
            }
        }

        if ($request->directOnly === true && \count($legs) > 1) {
            $reasons[] = $direction->value . ' contains connection';
        }

        return [$legs, $reasons];
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array{0: ?FlightOfferLegCandidate, 1: string[]}
     */
    private function leg(array $item, FlightDirection $direction, int $index): array
    {
        $reasons = [];
        $originIata = $this->iata($item['originIata'] ?? $item['origin'] ?? null);
        $destinationIata = $this->iata($item['destinationIata'] ?? $item['destination'] ?? null);
        $departureAt = $this->dateTime($item['departureAt'] ?? null);
        $arrivalAt = $this->dateTime($item['arrivalAt'] ?? null);

        if ($originIata === null || $this->airportRepository->findOneBy(['iataCode' => $originIata]) === null) {
            $reasons[] = 'origin airport IATA missing or unknown';
        }
        if ($destinationIata === null || $this->airportRepository->findOneBy(['iataCode' => $destinationIata]) === null) {
            $reasons[] = 'destination airport IATA missing or unknown';
        }
        if (!$departureAt instanceof \DateTimeImmutable) {
            $reasons[] = 'departureAt missing or invalid';
        }
        if (!$arrivalAt instanceof \DateTimeImmutable) {
            $reasons[] = 'arrivalAt missing or invalid';
        }
        if ($reasons !== []) {
            return [null, $reasons];
        }

        try {
            return [new FlightOfferLegCandidate(
                direction: $direction,
                segmentIndex: $index,
                airlineName: $this->string($item['airlineName'] ?? null),
                airlineIata: $this->airlineCode($item['airlineIata'] ?? null, 3),
                airlineIcao: $this->airlineCode($item['airlineIcao'] ?? null, 4),
                originIata: (string) $originIata,
                destinationIata: (string) $destinationIata,
                flightNumber: $this->string($item['flightNumber'] ?? null),
                departureAt: $departureAt,
                arrivalAt: $arrivalAt,
                durationMinutes: $this->integer($item['durationMinutes'] ?? null),
                aircraft: $this->string($item['aircraft'] ?? null),
                metadata: ['rawProviderLeg' => $item],
            ), []];
        } catch (\InvalidArgumentException $exception) {
            return [null, [$exception->getMessage()]];
        }
    }

    private function currency(mixed $value): ?string
    {
        $value = \is_string($value) ? strtoupper(trim($value)) : '';

        return preg_match('/^[A-Z]{3}$/', $value) === 1 ? $value : null;
    }

    private function iata(mixed $value): ?string
    {
        $value = \is_string($value) ? strtoupper(trim($value)) : '';

        return preg_match('/^[A-Z]{3}$/', $value) === 1 ? $value : null;
    }

    private function airlineCode(mixed $value, int $max): ?string
    {
        $value = \is_string($value) ? strtoupper(trim($value)) : '';

        return $value !== '' && preg_match('/^[A-Z0-9]{1,' . $max . '}$/', $value) === 1 ? $value : null;
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

    private function dateTime(mixed $value): ?\DateTimeImmutable
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

    private function tripType(mixed $value): ?FlightTripType
    {
        $value = \is_string($value) ? strtolower(trim($value)) : '';
        $value = str_replace(['-', ' '], '_', $value);

        return FlightTripType::tryFrom($value);
    }

    private function cabinClass(mixed $value): ?FlightCabinClass
    {
        $value = \is_string($value) ? strtolower(trim($value)) : '';
        $value = str_replace(['-', ' '], '_', $value);

        return FlightCabinClass::tryFrom($value);
    }

    private function availabilityStatus(mixed $value): FlightAvailabilityStatus
    {
        $value = \is_string($value) ? strtolower(trim($value)) : '';
        $value = str_replace(['-', ' '], '_', $value);

        return FlightAvailabilityStatus::tryFrom($value) ?? FlightAvailabilityStatus::UNKNOWN;
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

    private function domainMatches(string $hostOrDomain, string $domain): bool
    {
        $host = strtolower(preg_replace('/^www\./', '', trim($hostOrDomain)) ?? $hostOrDomain);
        $host = explode('/', $host)[0];
        $domain = strtolower(preg_replace('/^www\./', '', trim($domain)) ?? $domain);

        return $host === $domain || str_ends_with($host, '.' . $domain);
    }

    private function dedupeKey(FlightOfferCandidate $candidate): string
    {
        if ($candidate->externalOfferId !== null) {
            return 'id:' . $candidate->externalOfferId;
        }

        return implode('|', [
            $candidate->providerCode,
            $candidate->currency,
            $candidate->totalPrice,
            $candidate->firstOutbound()->originIata,
            $candidate->lastOutbound()->destinationIata,
            $candidate->firstOutbound()->departureAt->format(\DateTimeInterface::ATOM),
            $candidate->bookingUrl ?? '',
        ]);
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
