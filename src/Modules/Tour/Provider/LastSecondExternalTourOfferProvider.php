<?php

namespace App\Modules\Tour\Provider;

use App\Modules\Hotel\Enum\HotelBoardType;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\Tour\Entity\TourPackage;
use App\Modules\Tour\Enum\TourAvailabilityStatus;
use App\Modules\Tour\ValueObject\ExternalTourOfferCandidate;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchRequest;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchResult;
use App\Modules\Tour\ValueObject\TourMoney;
use App\Shared\Date\LocaleDateTimeFormatter;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class LastSecondExternalTourOfferProvider implements ExternalTourOfferProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LocaleDateTimeFormatter $dateFormatter,
    ) {
    }

    public function supports(SearchSource $source): bool
    {
        return $source->getProvider() === 'lastsecond'
            && $source->getProviderType() === SearchSourceProviderType::API
            && $source->supports(SearchSource::CAPABILITY_TOUR);
    }

    public function search(SearchSource $source, ExternalTourOfferSearchRequest $request): ExternalTourOfferSearchResult
    {
        $url = $this->requestUrl($source, $request);
        if ($url === null) {
            return ExternalTourOfferSearchResult::noData($source, [
                'provider' => 'lastsecond',
                'accessStrategy' => 'lastsecond_json',
                'noDataReason' => 'SearchSource config must include lastSecondApiUrl or tourApiUrl.',
                'rawResultCount' => 0,
                'acceptedCandidateCount' => 0,
                'rejectedCandidateCount' => 0,
            ]);
        }

        $payload = $this->requestPayload($source, $request);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Accept' => 'application/json, text/plain, */*',
                    'Content-Type' => 'application/json',
                    'Origin' => 'https://lastsecond.ir',
                    'Referer' => 'https://lastsecond.ir/tours',
                    'User-Agent' => 'Mozilla/5.0 (compatible; AlefBayeSafar/1.0)',
                ],
                'json' => $payload,
            ]);
            $statusCode = $response->getStatusCode();
            $headers = $response->getHeaders(false);
            $content = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            return ExternalTourOfferSearchResult::failure($source, ['LastSecond request failed: ' . $exception->getMessage()], [
                'provider' => 'lastsecond',
                'accessStrategy' => 'lastsecond_json',
                'sourceUrlUsed' => $url,
                'httpMethod' => 'POST',
                'requestPayload' => $payload,
            ]);
        }

        $contentType = $headers['content-type'][0] ?? null;

        if ($statusCode >= 400) {
            return ExternalTourOfferSearchResult::failure($source, [sprintf('LastSecond HTTP %d', $statusCode)], [
                'provider' => 'lastsecond',
                'accessStrategy' => 'lastsecond_json',
                'sourceUrlUsed' => $url,
                'httpMethod' => 'POST',
                'requestPayload' => $payload,
                'httpStatus' => $statusCode,
                'contentType' => $contentType,
                'jsonDecoded' => false,
            ]);
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            $jsonDecoded = true;
        } catch (\JsonException) {
            return ExternalTourOfferSearchResult::noData($source, [
                'provider' => 'lastsecond',
                'accessStrategy' => 'lastsecond_json',
                'sourceUrlUsed' => $url,
                'httpMethod' => 'POST',
                'requestPayload' => $payload,
                'httpStatus' => $statusCode,
                'contentType' => $contentType,
                'jsonDecoded' => false,
                'noDataReason' => 'LastSecond response was not JSON.',
            ]);
        }

        if (!\is_array($decoded)) {
            return ExternalTourOfferSearchResult::noData($source, [
                'provider' => 'lastsecond',
                'accessStrategy' => 'lastsecond_json',
                'sourceUrlUsed' => $url,
                'httpMethod' => 'POST',
                'requestPayload' => $payload,
                'httpStatus' => $statusCode,
                'contentType' => $contentType,
                'jsonDecoded' => $jsonDecoded,
                'noDataReason' => 'LastSecond response was not a JSON object.',
            ]);
        }

        $offers = $this->offerRows($decoded);
        $candidates = [];
        $diagnostics = [];
        foreach ($offers as $index => $offer) {
            [$candidate, $reasons] = $this->candidateFromOffer($source, $request, $offer);
            if ($candidate instanceof ExternalTourOfferCandidate) {
                $candidates[$candidate->externalOfferId ?? $index] = $candidate;
                $diagnostics[] = [
                    'index' => $index,
                    'accepted' => true,
                    'normalized' => [
                        'title' => $candidate->title,
                        'origin' => $candidate->originText,
                        'destination' => $candidate->destinationText,
                        'hotelName' => $candidate->hotelName,
                        'totalPrice' => $candidate->totalPrice,
                        'currency' => $candidate->currency,
                    ],
                ];
                continue;
            }

            $diagnostics[] = [
                'index' => $index,
                'accepted' => false,
                'reasons' => $reasons !== [] ? $reasons : ['MALFORMED_LASTSECOND_OFFER'],
            ];
        }

        $metadata = [
            'provider' => 'lastsecond',
            'accessStrategy' => 'lastsecond_json',
            'sourceUrlUsed' => $url,
            'httpMethod' => 'POST',
            'requestPayload' => $payload,
            'httpStatus' => $statusCode,
            'contentType' => $contentType,
            'jsonDecoded' => $jsonDecoded,
            'topLevelKeys' => array_keys($decoded),
            'rawResultCount' => \count($offers),
            'acceptedCandidateCount' => \count($candidates),
            'rejectedCandidateCount' => \count(array_filter($diagnostics, static fn (array $diagnostic): bool => $diagnostic['accepted'] === false)),
            'offerDiagnostics' => $diagnostics,
        ];

        if ($offers === []) {
            return ExternalTourOfferSearchResult::noResults($source, $metadata);
        }

        return $candidates !== []
            ? ExternalTourOfferSearchResult::success($source, array_values($candidates), $metadata)
            : ExternalTourOfferSearchResult::noData($source, $metadata + ['noDataReason' => 'No LastSecond offer passed strict normalization.']);
    }

    private function requestUrl(SearchSource $source, ExternalTourOfferSearchRequest $request): ?string
    {
        $config = $source->getConfig();
        $template = $this->string($config['lastSecondApiUrl'] ?? $config['tourApiUrl'] ?? null);
        if ($template === null) {
            return null;
        }

        $query = [
            'origin' => $request->originAirport?->getCity()?->getName(),
            'destination' => $request->destinationCity->getName(),
            'from' => $request->departureDate?->format('Y-m-d') ?? $request->validFrom?->format('Y-m-d'),
            'to' => $request->returnDate?->format('Y-m-d') ?? $request->validTo?->format('Y-m-d'),
            'nights' => $request->nights,
            'adults' => $request->adults,
            'children' => $request->children,
            'infants' => $request->infants,
            'rooms' => $request->rooms,
        ];

        $url = strtr($template, [
            '{origin}' => rawurlencode((string) ($query['origin'] ?? '')),
            '{destination}' => rawurlencode((string) $query['destination']),
            '{from}' => (string) ($query['from'] ?? ''),
            '{to}' => (string) ($query['to'] ?? ''),
            '{nights}' => (string) ($query['nights'] ?? ''),
            '{adults}' => (string) $request->adults,
            '{children}' => (string) $request->children,
            '{infants}' => (string) $request->infants,
            '{rooms}' => (string) $request->rooms,
        ]);

        return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(SearchSource $source, ExternalTourOfferSearchRequest $request): array
    {
        $config = $source->getConfig();
        $originCityId = $request->originAirport?->getCity()?->getId();
        $destinationCityId = $request->destinationCity->getId();
        $sourceId = $this->mappedInteger($config['lastSecondSourceIdsByOriginCityId'] ?? null, $originCityId)
            ?? $this->integer($config['lastSecondSourceId'] ?? null);
        $locationId = $this->mappedInteger($config['lastSecondLocationIdsByDestinationCityId'] ?? null, $destinationCityId)
            ?? $this->integer($config['lastSecondLocationId'] ?? null);

        return [
            'location' => $locationId ?? false,
            'tourUrl' => false,
            'type' => false,
            'tourAlias' => false,
            'filters' => [
                'startDate' => new \stdClass(),
                'inDays' => null,
                'endDate' => new \stdClass(),
                'startRange' => [],
                'endRange' => [],
                'minPrice' => null,
                'maxPrice' => null,
                'onlineOnly' => false,
                'discountedTours' => false,
                'directTours' => false,
                'multiLocation' => false,
                'keywords' => [],
                'locations' => [],
                'airlines' => [],
                'agencies' => [],
                'sources' => $sourceId !== null ? [$sourceId] : [],
                'tourTypes' => [],
                'travelTypes' => [],
                'durations' => $request->nights !== null ? [$request->nights] : [],
                'tourUrls' => [],
                'hotels' => [],
                'hotelKeywords' => [],
                'hotelGrades' => [],
                'hotelServices' => [],
                'hotelReviewScore' => 0,
                'hotelRoomTypes' => [],
                'months' => [],
                'refundGuaranteeTours' => false,
            ],
            'perPage' => $this->integer($config['lastSecondPerPage'] ?? null) ?? 20,
            'page' => $this->integer($config['lastSecondPage'] ?? null) ?? 1,
            'sort' => $this->integer($config['lastSecondSort'] ?? null) ?? 3,
        ];
    }

    /**
     * @param array<mixed> $decoded
     *
     * @return array<int, array<string, mixed>>
     */
    private function offerRows(array $decoded): array
    {
        foreach (['data', 'items', 'treks', 'tours', 'results'] as $key) {
            if (\is_array($decoded[$key] ?? null) && array_is_list($decoded[$key])) {
                return array_values(array_filter($decoded[$key], static fn (mixed $row): bool => \is_array($row)));
            }
        }

        if (isset($decoded[0]) && array_is_list($decoded)) {
            return array_values(array_filter($decoded, static fn (mixed $row): bool => \is_array($row)));
        }

        return [];
    }

    /**
     * @param array<string, mixed> $offer
     *
     * @return array{0: ?ExternalTourOfferCandidate, 1: string[]}
     */
    private function candidateFromOffer(SearchSource $source, ExternalTourOfferSearchRequest $request, array $offer): array
    {
        $reasons = [];
        $title = $this->string($offer['title'] ?? $offer['tourTitle'] ?? $offer['titleEn'] ?? $offer['name'] ?? $offer['nameEn'] ?? null);
        $origin = $this->originText($offer);
        $destination = $this->destinationText($offer);
        $hotel = $this->firstHotel($offer);
        $prices = $this->prices($offer);
        $price = $this->minimumPrice($prices);
        $currency = $price['currency'] ?? $this->currency($offer['currency'] ?? null) ?? 'IRR';
        $departureDate = $this->date($offer['startDate'] ?? $offer['departureDate'] ?? $offer['fromDate'] ?? null);
        $returnDate = $this->date($offer['endDate'] ?? $offer['returnDate'] ?? $offer['toDate'] ?? null);
        $nights = $this->integer($offer['nights'] ?? $offer['duration'] ?? null);
        $days = $this->integer($offer['days'] ?? null);
        if ($nights === null && $days !== null && $days > 0) {
            $nights = max(1, $days - 1);
        }

        if ($title === null) {
            $reasons[] = 'MISSING_TITLE';
        }
        if ($destination === null) {
            $reasons[] = 'MISSING_DESTINATION';
        } elseif (!$this->matchesDestination($destination, $request)) {
            $reasons[] = 'DESTINATION_MISMATCH';
        }
        if ($price === null) {
            $reasons[] = 'MISSING_PRICE';
        }
        if ($request->nights !== null && $nights !== null && $request->nights !== $nights) {
            $reasons[] = 'NIGHTS_MISMATCH';
        }
        if (!$departureDate instanceof \DateTimeImmutable && !$returnDate instanceof \DateTimeImmutable && !$request->validFrom instanceof \DateTimeImmutable) {
            $reasons[] = 'MISSING_DATE_CONTEXT';
        }

        if ($reasons !== []) {
            return [null, array_values(array_unique($reasons))];
        }

        return [new ExternalTourOfferCandidate(
            sourceIdentifier: ExternalTourOfferCandidate::sourceIdentifier($source),
            sourceName: $source->getName(),
            providerCode: 'lastsecond',
            externalOfferId: $this->string($offer['id'] ?? $offer['uuid'] ?? $offer['slug'] ?? null),
            title: (string) $title,
            originText: $origin,
            destinationText: (string) $destination,
            departureDate: $departureDate,
            returnDate: $returnDate,
            validFrom: $departureDate,
            validTo: $returnDate,
            nights: $nights,
            days: $days,
            hotelName: $hotel['hotelName'],
            roomName: $hotel['roomName'],
            boardType: $this->board($hotel['boardType']),
            flightSummary: $this->flightSummary($offer),
            adults: $request->adults,
            children: $request->children,
            infants: $request->infants,
            childrenAges: $request->childrenAges,
            inclusions: $this->inclusions($offer),
            exclusions: [],
            currency: (string) $currency,
            totalPrice: (string) $price['amount'],
            bookingUrl: $this->bookingUrl($source, $offer),
            availabilityStatus: TourAvailabilityStatus::AVAILABLE,
            metadata: [
                'rawProviderOffer' => $offer,
                'agency' => $this->agency($offer),
                'hotelStars' => $hotel['stars'],
                'rawSegmentPrices' => $prices,
                'comparisonPriceSource' => 'minimum_positive_source_price',
                'priceInterpretation' => 'Source minimum comparison price; segment semantics not inferred.',
            ],
        ), []];
    }

    /**
     * @param array<string, mixed> $offer
     */
    private function originText(array $offer): ?string
    {
        $source = \is_array($offer['source'] ?? null) ? $offer['source'] : [];

        return $this->string($source['titleEn'] ?? $source['title'] ?? $offer['origin'] ?? $offer['originTitle'] ?? null);
    }

    /**
     * @param array<string, mixed> $offer
     */
    private function destinationText(array $offer): ?string
    {
        foreach ($this->list($offer['locations'] ?? []) as $location) {
            $locationData = \is_array($location['location'] ?? null) ? $location['location'] : $location;
            $name = $this->string($locationData['titleEn'] ?? $locationData['title'] ?? $locationData['nameEn'] ?? $locationData['name'] ?? null);
            if ($name !== null) {
                return $name;
            }
        }

        return $this->string($offer['destination'] ?? $offer['destinationTitle'] ?? null);
    }

    /**
     * @param array<string, mixed> $offer
     *
     * @return array{hotelName: ?string, roomName: ?string, boardType: ?string, stars: int|null}
     */
    private function firstHotel(array $offer): array
    {
        foreach ($this->list($offer['hotels'] ?? []) as $hotelRow) {
            $hotel = \is_array($hotelRow['hotel'] ?? null) ? $hotelRow['hotel'] : $hotelRow;
            $service = \is_array($hotelRow['service'] ?? null) ? $hotelRow['service'] : [];
            $roomType = \is_array($hotelRow['roomType'] ?? null) ? $hotelRow['roomType'] : [];

            return [
                'hotelName' => $this->string($hotel['titleEn'] ?? $hotel['title'] ?? $hotel['nameEn'] ?? $hotel['name'] ?? null),
                'roomName' => $this->string($roomType['titleEn'] ?? $roomType['title'] ?? $hotelRow['roomType'] ?? $hotelRow['room'] ?? null),
                'boardType' => $this->string($service['key'] ?? $service['title'] ?? $hotelRow['service'] ?? $hotelRow['board'] ?? null),
                'stars' => $this->integer($hotel['stars'] ?? $hotel['star'] ?? $hotelRow['stars'] ?? null),
            ];
        }

        return ['hotelName' => null, 'roomName' => null, 'boardType' => null, 'stars' => null];
    }

    /**
     * @param array<string, mixed> $offer
     *
     * @return array<int, array{amount: string, currency: string, label: string|null, raw: mixed}>
     */
    private function prices(array $offer): array
    {
        $prices = [];
        foreach ($this->list($offer['prices'] ?? $offer['priceData'] ?? []) as $priceRow) {
            foreach ($this->list($priceRow['values'] ?? []) as $valueRow) {
                $amount = TourMoney::normalize($valueRow['price'] ?? $valueRow['amount'] ?? $valueRow['value'] ?? null);
                if ($amount === null) {
                    continue;
                }
                $prices[] = [
                    'amount' => $amount,
                    'currency' => $this->currency($valueRow['currency'] ?? $priceRow['currency'] ?? null) ?? 'IRR',
                    'label' => $this->string($priceRow['title'] ?? $priceRow['label'] ?? $priceRow['segment'] ?? null),
                    'raw' => $priceRow,
                ];
            }

            $amount = TourMoney::normalize($priceRow['price'] ?? $priceRow['amount'] ?? $priceRow['value'] ?? null);
            if ($amount === null) {
                continue;
            }
            $prices[] = [
                'amount' => $amount,
                'currency' => $this->currency($priceRow['currency'] ?? null) ?? 'IRR',
                'label' => $this->string($priceRow['title'] ?? $priceRow['label'] ?? $priceRow['segment'] ?? null),
                'raw' => $priceRow,
            ];
        }

        $amount = TourMoney::normalize($offer['price'] ?? $offer['minPrice'] ?? $offer['totalPrice'] ?? null);
        if ($amount !== null) {
            $prices[] = ['amount' => $amount, 'currency' => $this->currency($offer['currency'] ?? null) ?? 'IRR', 'label' => 'offer', 'raw' => $offer['price'] ?? $offer['minPrice'] ?? $offer['totalPrice']];
        }

        return $prices;
    }

    /**
     * @param array<int, array{amount: string, currency: string, label: string|null, raw: mixed}> $prices
     *
     * @return array{amount: string, currency: string}|null
     */
    private function minimumPrice(array $prices): ?array
    {
        $minimum = null;
        foreach ($prices as $price) {
            if (!TourMoney::isPositiveDecimal($price['amount'])) {
                continue;
            }
            if ($minimum === null || TourMoney::cents($price['amount']) < TourMoney::cents($minimum['amount'])) {
                $minimum = ['amount' => $price['amount'], 'currency' => $price['currency']];
            }
        }

        return $minimum;
    }

    /**
     * @param array<string, mixed> $offer
     */
    private function flightSummary(array $offer): ?string
    {
        $airlineData = \is_array($offer['airline'] ?? null) ? $offer['airline'] : [];
        $airline = $this->string($airlineData['titleEn'] ?? $airlineData['titleFa'] ?? $offer['airline'] ?? $offer['airlineName'] ?? null);
        if ($airline !== null) {
            return $airline;
        }

        foreach ($this->list($offer['flights'] ?? []) as $flight) {
            $airline = $this->string($flight['airline'] ?? $flight['airlineName'] ?? null);
            if ($airline !== null) {
                return $airline;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $offer
     *
     * @return string[]
     */
    private function inclusions(array $offer): array
    {
        $keys = ['hotel'];
        if ($this->flightSummary($offer) !== null) {
            $keys[] = 'flight';
        }

        $hotel = $this->firstHotel($offer);
        if ($this->board($hotel['boardType']) === HotelBoardType::BREAKFAST->value) {
            $keys[] = 'breakfast';
        }

        return TourPackage::normalizeInclusionKeys($keys);
    }

    /**
     * @param array<string, mixed> $offer
     */
    private function agency(array $offer): ?string
    {
        $agency = \is_array($offer['agency'] ?? null) ? $offer['agency'] : [];

        return $this->string($agency['titleEn'] ?? $agency['titleFa'] ?? $agency['title'] ?? $agency['name'] ?? $offer['agencyName'] ?? $offer['agencyTitle'] ?? null);
    }

    /**
     * @param array<string, mixed> $offer
     */
    private function bookingUrl(SearchSource $source, array $offer): ?string
    {
        $value = $this->string($offer['url'] ?? $offer['link'] ?? $offer['bookingUrl'] ?? null);
        if ($value === null) {
            return null;
        }
        if (str_starts_with($value, '/')) {
            $baseUrl = $this->string($source->getConfig()['lastSecondPublicBaseUrl'] ?? null);
            if ($baseUrl !== null && filter_var($baseUrl, FILTER_VALIDATE_URL) !== false) {
                return rtrim($baseUrl, '/') . $value;
            }

            return 'https://' . $source->getDomain() . $value;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false ? $value : null;
    }

    private function board(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return HotelBoardType::normalize($value)?->value;
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        $value = $this->string($value);
        if ($value === null) {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T/', $value) === 1) {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                return null;
            }
        }

        $gregorian = $this->dateFormatter->parseDate($value, 'en');
        if ($gregorian instanceof \DateTimeImmutable) {
            return $gregorian;
        }

        return $this->dateFormatter->parseDate($value, 'fa');
    }

    private function currency(mixed $value): ?string
    {
        if (\is_int($value)) {
            return $value === 1 ? 'IRR' : null;
        }

        $value = $this->string($value);
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper($value);

        return match (true) {
            preg_match('/^[A-Z]{3}$/', $normalized) === 1 => $normalized,
            str_contains($value, 'تومان'), str_contains($normalized, 'TOMAN') => 'IRR',
            default => null,
        };
    }

    private function matchesDestination(string $destination, ExternalTourOfferSearchRequest $request): bool
    {
        $haystack = $this->normalizeText($destination);
        foreach ([$request->destinationCity->getName(), $request->destinationCity->getNameFa(), $request->destinationCity->getSlug()] as $needle) {
            $needle = $this->normalizeText((string) $needle);
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function list(mixed $value): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $row): bool => \is_array($row)));
    }

    private function integer(mixed $value): ?int
    {
        if (\is_int($value)) {
            return $value;
        }
        if (\is_string($value) && preg_match('/\d+/', $this->normalizeDigits($value), $matches) === 1) {
            return (int) $matches[0];
        }

        return null;
    }

    /**
     * @param array<mixed>|null $map
     */
    private function mappedInteger(mixed $map, ?int $key): ?int
    {
        if (!\is_array($map) || $key === null) {
            return null;
        }

        return $this->integer($map[(string) $key] ?? $map[$key] ?? null);
    }

    private function string(mixed $value): ?string
    {
        $value = \is_scalar($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
    }

    private function normalizeText(string $value): string
    {
        $value = $this->normalizeDigits($value);
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', strtolower($value));
        $normalized = \is_string($converted) && $converted !== '' ? $converted : strtolower($value);
        $normalized = preg_replace('/[^\p{L}0-9]+/u', ' ', $normalized) ?? '';

        return trim($normalized);
    }

    private function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);
    }
}
