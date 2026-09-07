<?php

namespace App\Modules\TripPlanner\Service;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Repository\CityRepository;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\Flight\Service\FlightOfferSearchService;
use App\Modules\Flight\Service\FlightOfferStoreService;
use App\Modules\Flight\ValueObject\FlightOfferSearchRequest;
use App\Modules\Hotel\Repository\HotelRepository;
use App\Modules\Hotel\Service\HotelOfferSearchService;
use App\Modules\Hotel\Service\HotelOfferStoreService;
use App\Modules\Hotel\ValueObject\HotelOfferSearchRequest;
use App\Modules\Flight\ValueObject\FlightOfferSearchResult;
use App\Modules\Hotel\ValueObject\HotelOfferSearchResult;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Repository\SearchSourceRepository;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchResult;
use App\Modules\Tour\Service\ExternalTourOfferSearchService;
use App\Modules\Tour\Service\ExternalTourOfferStoreService;
use App\Modules\Tour\ValueObject\ExternalTourOfferCandidate;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchRequest;
use App\Modules\TripPlanner\Enum\TripPlanStatus;
use App\Modules\TripPlanner\ValueObject\TripPlanResult;
use App\Modules\TripPlanner\ValueObject\TripSearchRequest;

/**
 * Orchestrates independent search channels for one trip request.
 *
 * Architectural rule this class enforces: OWN data first, then LIVE external
 * search across Tour/Flight/Hotel independently, THEN (only for flight, only
 * if a direct-origin search truly found nothing) an alternative commercial
 * departure fallback. Tour search results may enrich the candidate date
 * windows used by Flight/Hotel, but Tour returning zero must never prevent
 * Flight or Hotel search from running — see independentDateWindows().
 */
final readonly class TravelPlanningService
{
    private const MAX_LIVE_DATE_WINDOWS = 3;
    private const MAX_HOTELS_FOR_LIVE_SEARCH = 3;
    private const MAX_COUNTRY_DISCOVERY_CITIES = 5;

    public function __construct(
        private PlanningIntentClarifier $clarifier,
        private JourneyStrategyPlanner $journeyStrategyPlanner,
        private ExternalTourOfferSearchService $tourSearchService,
        private ExternalTourOfferStoreService $tourStoreService,
        private FlightOfferSearchService $flightSearchService,
        private FlightOfferStoreService $flightStoreService,
        private SearchSourceRepository $searchSourceRepository,
        private CityRepository $cityRepository,
        private HotelRepository $hotelRepository,
        private HotelOfferSearchService $hotelSearchService,
        private HotelOfferStoreService $hotelStoreService,
        private CommercialDepartureResolver $departureResolver,
        private TripPlanner $tripPlanner,
    ) {
    }

    public function plan(TripSearchRequest $request): TripPlanResult
    {
        $clarification = $this->clarifier->evaluate($request);
        $journeys = $this->journeyStrategyPlanner->strategies($request);
        $diagnostics = [
            'clarification' => [
                'status' => $clarification->status->value,
                'missingCriticalFields' => $clarification->missingCriticalFields,
                'suggestedNextQuestionKey' => $clarification->suggestedNextQuestionKey,
            ],
            'journeyStrategies' => $this->journeyDiagnostics($journeys),
            'liveSearch' => [],
            'bounds' => [
                'maxLiveDateWindows' => self::MAX_LIVE_DATE_WINDOWS,
                'maxHotelsForLiveSearch' => self::MAX_HOTELS_FOR_LIVE_SEARCH,
                'maxCountryDiscoveryCities' => self::MAX_COUNTRY_DISCOVERY_CITIES,
            ],
        ];

        if (!$clarification->isReady()) {
            return new TripPlanResult(
                TripPlanStatus::INVALID_REQUEST,
                [],
                ['The request needs one more answer before searching can start.'],
                $this->emptyCounts(),
                $diagnostics,
            );
        }

        // Date windows are derived directly from the request FIRST, independently
        // of Tour results. Tour candidates may only ADD extra windows afterwards.
        $independentWindows = $this->independentDateWindows($request);
        $tourWindows = $this->liveTourSearch($request, $diagnostics);
        $mergedWindows = $this->mergeWindows($independentWindows, $tourWindows);
        $diagnostics['dateWindows'] = [
            'independent' => $this->windowsDiagnostics($independentWindows),
            'tourContributed' => $this->windowsDiagnostics($tourWindows),
            'merged' => $this->windowsDiagnostics($mergedWindows),
        ];

        $request = $request->withCandidateDateWindows($mergedWindows);
        $request = $this->liveFlightSearch($request, $mergedWindows, $diagnostics);
        $this->liveHotelSearch($request, $mergedWindows, $diagnostics);

        $result = $this->tripPlanner->plan($request);

        return new TripPlanResult(
            $result->status,
            $result->options,
            $result->messages,
            $result->counts,
            $diagnostics + $result->diagnostics,
        );
    }

    /**
     * Bounded, concrete date windows derived ONLY from the request itself
     * (exact dates, or windowStart/windowEnd/nights for flexible mode) — the
     * ONE source of date windows that is guaranteed regardless of whether
     * Tour search finds anything.
     *
     * @return array<int, array{departureDate: \DateTimeImmutable, returnDate: \DateTimeImmutable}>
     */
    private function independentDateWindows(TripSearchRequest $request): array
    {
        if (!$request->isFlexible()) {
            if ($request->departureDate instanceof \DateTimeImmutable && $request->returnDate instanceof \DateTimeImmutable) {
                return [['departureDate' => $request->departureDate, 'returnDate' => $request->returnDate]];
            }

            return [];
        }

        if (!$request->windowStart instanceof \DateTimeImmutable || !$request->windowEnd instanceof \DateTimeImmutable) {
            return [];
        }

        $nights = $request->nightsOrDerived();
        $latestDeparture = $request->windowEnd->modify('-' . $nights . ' days');
        if ($latestDeparture < $request->windowStart) {
            // The window is narrower than the requested stay — still search the one window it can fit.
            return [['departureDate' => $request->windowStart, 'returnDate' => $request->windowStart->modify('+' . $nights . ' days')]];
        }

        $totalSpanDays = (int) $request->windowStart->diff($latestDeparture)->days;
        $count = $totalSpanDays > 0 ? min(self::MAX_LIVE_DATE_WINDOWS, $totalSpanDays + 1) : 1;

        $windows = [];
        for ($i = 0; $i < $count; ++$i) {
            $offset = $count > 1 ? (int) round($totalSpanDays * $i / ($count - 1)) : 0;
            $departure = $request->windowStart->modify('+' . $offset . ' days');
            $key = $departure->format('Y-m-d');
            $windows[$key] = ['departureDate' => $departure, 'returnDate' => $departure->modify('+' . $nights . ' days')];
            if (\count($windows) >= self::MAX_LIVE_DATE_WINDOWS) {
                break;
            }
        }

        return array_values($windows);
    }

    /**
     * @param array<int, array{departureDate: \DateTimeImmutable, returnDate: \DateTimeImmutable}> $independent
     * @param array<int, array{departureDate: \DateTimeImmutable, returnDate: \DateTimeImmutable}> $tourWindows
     *
     * @return array<int, array{departureDate: \DateTimeImmutable, returnDate: \DateTimeImmutable}>
     */
    private function mergeWindows(array $independent, array $tourWindows): array
    {
        $merged = [];
        foreach (array_merge($independent, $tourWindows) as $window) {
            $key = $window['departureDate']->format('Y-m-d') . '|' . $window['returnDate']->format('Y-m-d');
            $merged[$key] ??= $window;
            if (\count($merged) >= self::MAX_LIVE_DATE_WINDOWS) {
                break;
            }
        }

        return array_values($merged);
    }

    /**
     * @param array<int, array{departureDate: \DateTimeImmutable, returnDate: \DateTimeImmutable}> $windows
     *
     * @return array<int, array{string, string}>
     */
    private function windowsDiagnostics(array $windows): array
    {
        return array_map(static fn (array $w): array => [$w['departureDate']->format('Y-m-d'), $w['returnDate']->format('Y-m-d')], $windows);
    }

    /**
     * @param array<string, mixed> $diagnostics
     *
     * @return array<int, array{departureDate: \DateTimeImmutable, returnDate: \DateTimeImmutable}>
     */
    private function liveTourSearch(TripSearchRequest $request, array &$diagnostics): array
    {
        $tourRequests = $this->tourRequests($request, $diagnostics);
        if ($tourRequests === []) {
            $diagnostics['liveSearch']['tour'] = ($diagnostics['liveSearch']['tour'] ?? []) + [
                'status' => 'skipped',
                'reason' => $request->destinationCountry() instanceof Country
                    ? 'No configured tour source and no active-airport city could be resolved for the selected country.'
                    : 'Destination-free external tour discovery is not supported by configured source contracts yet.',
            ];

            return [];
        }

        $stored = 0;
        $results = [];
        $allCandidates = [];
        try {
            foreach ($tourRequests as $tourRequest) {
                $summary = $this->tourSearchService->search($tourRequest);
                $stored += $this->tourStoreService->storeSummary($tourRequest, $summary);
                array_push($results, ...$summary->getResults());
                array_push($allCandidates, ...$summary->getCandidates());
            }
        } catch (\Throwable $exception) {
            $diagnostics['liveSearch']['tour'] = ['status' => 'provider_error', 'error' => $exception->getMessage()];

            return [];
        }

        $tourDiagnostics = $diagnostics['liveSearch']['tour'] ?? [];
        $diagnostics['liveSearch']['tour'] = $tourDiagnostics + [
            'sources' => $this->sourceDiagnostics($results),
            'rawCount' => array_sum(array_map(static fn ($result): int => (int) ($result->metadata['rawResultCount'] ?? 0), $results)),
            'acceptedCount' => \count($allCandidates),
            'storedCount' => $stored,
            'searches' => \count($tourRequests),
        ];

        return $this->dateWindowsFromTourCandidates($allCandidates, $request);
    }

    /**
     * @param array<string, mixed> $diagnostics
     *
     * @return ExternalTourOfferSearchRequest[]
     */
    private function tourRequests(TripSearchRequest $request, array &$diagnostics): array
    {
        if ($request->destinationCity instanceof City) {
            return [$this->tourRequestForCity($request, $request->destinationCity)];
        }

        $country = $request->destinationCountry();
        if (!$country instanceof Country) {
            return [];
        }

        $cities = $this->discoverDestinationCitiesForCountry($country);
        $diagnostics['liveSearch']['tour']['countryDiscovery'] = [
            'country' => $country->getName(),
            'cities' => array_map(static fn (City $city): string => $city->getName(), $cities),
            'bound' => self::MAX_COUNTRY_DISCOVERY_CITIES,
        ];

        return array_map(fn (City $city): ExternalTourOfferSearchRequest => $this->tourRequestForCity($request, $city), $cities);
    }

    private function tourRequestForCity(TripSearchRequest $request, City $city): ExternalTourOfferSearchRequest
    {
        return new ExternalTourOfferSearchRequest(
            $request->originAirport,
            $city,
            $request->isFlexible() ? null : $request->departureDate,
            $request->isFlexible() ? null : $request->returnDate,
            $request->isFlexible() ? $request->windowStart : null,
            $request->isFlexible() ? $request->windowEnd : null,
            $request->nights,
            $request->adults,
            $request->children,
            $request->infants,
            $request->childAges,
            $request->budget,
            $request->preferredCurrency,
            $request->rooms,
        );
    }

    /**
     * Bounded candidate destination cities for a country-only request.
     * Priority: cities a configured tour source already names as supported,
     * then (only if none are configured) any active city that has a real
     * active airport. Never a hardcoded global list.
     *
     * @return City[]
     */
    private function discoverDestinationCitiesForCountry(Country $country): array
    {
        $configured = $this->configuredTourCitiesForCountry($country);
        if ($configured !== []) {
            return $configured;
        }

        return $this->cityRepository->findActiveWithAirportForCountry($country, self::MAX_COUNTRY_DISCOVERY_CITIES);
    }

    /**
     * @return City[]
     */
    private function configuredTourCitiesForCountry(Country $country): array
    {
        $ids = [];
        foreach ($this->searchSourceRepository->findEnabledForCapability(SearchSource::CAPABILITY_TOUR) as $source) {
            $config = $source->getConfig();
            foreach (['supportedDestinationCityIds', 'lastSecondLocationIdsByDestinationCityId'] as $key) {
                $value = $config[$key] ?? [];
                if (!\is_array($value)) {
                    continue;
                }
                foreach ($key === 'lastSecondLocationIdsByDestinationCityId' ? array_keys($value) : $value as $id) {
                    if (\is_int($id) || (\is_string($id) && ctype_digit($id))) {
                        $ids[] = (int) $id;
                    }
                }
            }
        }

        $cities = [];
        foreach (array_values(array_unique($ids)) as $id) {
            $city = $this->cityRepository->find($id);
            if ($city instanceof City && $city->isActive() && $city->getCountry()?->getId() === $country->getId()) {
                $cities[$id] = $city;
            }
            if (\count($cities) >= self::MAX_COUNTRY_DISCOVERY_CITIES) {
                break;
            }
        }

        return array_values($cities);
    }

    /**
     * Runs live flight search independently of Tour. Tries the user's real,
     * direct origin first (for every candidate destination city — a single
     * city for a concrete destination, or a bounded discovered set for a
     * country-only destination). Only when the direct-origin search across
     * ALL candidate destinations produces zero accepted results does it fall
     * back to a bounded, dynamically-discovered alternative departure city.
     *
     * @param array<int, array{departureDate: \DateTimeImmutable, returnDate: \DateTimeImmutable}> $windows
     * @param array<string, mixed> $diagnostics
     */
    private function liveFlightSearch(TripSearchRequest $request, array $windows, array &$diagnostics): TripSearchRequest
    {
        if (!$request->originAirport instanceof Airport) {
            $diagnostics['liveSearch']['flight'] = ['status' => 'skipped', 'reason' => 'No origin airport was resolved for this request.'];

            return $request;
        }

        if ($windows === []) {
            $diagnostics['liveSearch']['flight'] = ['status' => 'skipped', 'reason' => 'No concrete date windows were available for external flight search.'];

            return $request;
        }

        $destinationCities = $this->resolveFlightDestinationCities($request, $diagnostics);
        if ($destinationCities === []) {
            $diagnostics['liveSearch']['flight']['status'] = 'skipped';
            $diagnostics['liveSearch']['flight']['reason'] = 'No destination city with an active airport could be resolved for external flight search.';

            return $request;
        }

        $perCity = [];
        $totalAccepted = 0;
        $resolvedCity = null;
        foreach ($destinationCities as $city) {
            $destinationAirport = $this->firstActiveAirport($city);
            if (!$destinationAirport instanceof Airport) {
                $perCity[$city->getName()] = ['status' => 'skipped', 'reason' => 'City has no active airport.'];
                continue;
            }

            [$accepted, $searches, $stored] = $this->runFlightSearchWindows($request->originAirport, $destinationAirport, $request, $windows);
            $perCity[$city->getName()] = ['searches' => $searches, 'acceptedCount' => $accepted, 'storedCount' => $stored];
            $totalAccepted += $accepted;
            if ($accepted > 0 && $resolvedCity === null) {
                $resolvedCity = $city;
            }
        }

        $diagnostics['liveSearch']['flight']['direct'] = [
            'originAirport' => $request->originAirport->getIataCode() ?? $request->originAirport->getName(),
            'destinations' => $perCity,
            'acceptedTotal' => $totalAccepted,
        ];

        if ($request->destinationCity === null && $resolvedCity instanceof City) {
            $request = $request->withDestinationCity($resolvedCity);
        }

        if ($totalAccepted > 0) {
            $diagnostics['liveSearch']['flight']['fallback'] = ['attempted' => false, 'reason' => 'Direct-origin search already produced a usable result.'];

            return $request;
        }

        if (!$request->originCity instanceof City) {
            $diagnostics['liveSearch']['flight']['fallback'] = ['attempted' => false, 'reason' => 'No origin city was known to resolve alternative departures.'];

            return $request;
        }

        if (!$request->destinationCity instanceof City) {
            $diagnostics['liveSearch']['flight']['fallback'] = ['attempted' => false, 'reason' => 'Fallback is only evaluated against one concrete destination city, and none was resolved.'];

            return $request;
        }

        return $this->attemptAlternativeDeparture($request, $windows, $diagnostics);
    }

    /**
     * @param array<string, mixed> $diagnostics
     *
     * @return City[]
     */
    private function resolveFlightDestinationCities(TripSearchRequest $request, array &$diagnostics): array
    {
        if ($request->destinationCity instanceof City) {
            return [$request->destinationCity];
        }

        $country = $request->destinationCountry();
        if (!$country instanceof Country) {
            return [];
        }

        $cities = $this->discoverDestinationCitiesForCountry($country);
        $diagnostics['liveSearch']['flight']['countryDiscovery'] = [
            'country' => $country->getName(),
            'cities' => array_map(static fn (City $city): string => $city->getName(), $cities),
            'bound' => self::MAX_COUNTRY_DISCOVERY_CITIES,
        ];

        return $cities;
    }

    /**
     * Only reached when the user's real, direct origin produced zero
     * accepted live flight results for the resolved destination. Discovers a
     * bounded (max 3), dynamically-ranked set of alternative departure
     * cities and stops at the first one that produces a real, validated
     * accepted result. The user's own origin is never overwritten — only
     * TripSearchRequest::$commercialDepartureAirport is attached.
     *
     * @param array<int, array{departureDate: \DateTimeImmutable, returnDate: \DateTimeImmutable}> $windows
     * @param array<string, mixed> $diagnostics
     */
    private function attemptAlternativeDeparture(TripSearchRequest $request, array $windows, array &$diagnostics): TripSearchRequest
    {
        $fallback = ['attempted' => true, 'candidates' => []];

        $candidates = $this->departureResolver->candidates($request->originCity);
        if ($candidates === []) {
            $fallback['reason'] = 'No alternative departure city with a real active airport was found in the origin country.';
            $diagnostics['liveSearch']['flight']['fallback'] = $fallback;

            return $request;
        }

        $destinationAirport = $this->firstActiveAirport($request->destinationCity);
        if (!$destinationAirport instanceof Airport) {
            $fallback['reason'] = 'Destination city has no active airport for a fallback search.';
            $diagnostics['liveSearch']['flight']['fallback'] = $fallback;

            return $request;
        }

        foreach ($candidates as $city) {
            if ($request->destinationCity instanceof City && $city->getId() === $request->destinationCity->getId()) {
                $fallback['candidates'][] = ['city' => $city->getName(), 'status' => 'skipped', 'reason' => 'Candidate is the destination itself, not a usable departure city.'];
                continue;
            }

            $airport = $this->departureResolver->primaryAirport($city);
            if (!$airport instanceof Airport) {
                $fallback['candidates'][] = ['city' => $city->getName(), 'status' => 'skipped', 'reason' => 'No active airport.'];
                continue;
            }

            [$accepted, $searches, $stored] = $this->runFlightSearchWindows($airport, $destinationAirport, $request, $windows);
            $fallback['candidates'][] = [
                'city' => $city->getName(),
                'airport' => $airport->getIataCode() ?? $airport->getName(),
                'searches' => $searches,
                'acceptedCount' => $accepted,
                'storedCount' => $stored,
            ];

            if ($accepted > 0) {
                $fallback['resolvedCity'] = $city->getName();
                $diagnostics['liveSearch']['flight']['fallback'] = $fallback;

                return $request->withCommercialDepartureAirport($airport);
            }
        }

        $fallback['reason'] = 'No candidate departure city produced a usable live flight result.';
        $diagnostics['liveSearch']['flight']['fallback'] = $fallback;

        return $request;
    }

    /**
     * @param array<int, array{departureDate: \DateTimeImmutable, returnDate: \DateTimeImmutable}> $windows
     *
     * @return array{0: int, 1: array<int, array<string, mixed>>, 2: int} accepted count, per-window diagnostics, stored count
     */
    private function runFlightSearchWindows(Airport $origin, Airport $destination, TripSearchRequest $request, array $windows): array
    {
        $stored = 0;
        $accepted = 0;
        $searches = [];
        foreach ($windows as $window) {
            try {
                $flightRequest = new FlightOfferSearchRequest($origin, $destination, $window['departureDate'], $window['returnDate'], $request->adults, $request->children, $request->infants, $request->flightCabin ?? FlightCabinClass::ECONOMY, $request->directFlightPreferred ? true : null);
                $summary = $this->flightSearchService->search($flightRequest);
                $stored += $this->flightStoreService->storeSummary($flightRequest, $summary);
                $count = \count($summary->getCandidates());
                $accepted += $count;
                $searches[] = ['window' => [$window['departureDate']->format('Y-m-d'), $window['returnDate']->format('Y-m-d')], 'sources' => $this->sourceDiagnostics($summary->getResults()), 'acceptedCount' => $count];
            } catch (\Throwable $exception) {
                $searches[] = ['window' => [$window['departureDate']->format('Y-m-d'), $window['returnDate']->format('Y-m-d')], 'status' => 'provider_error', 'error' => $exception->getMessage()];
            }
        }

        return [$accepted, $searches, $stored];
    }

    private function firstActiveAirport(City $city): ?Airport
    {
        foreach ($city->getAirports() as $airport) {
            if ($airport instanceof Airport && $airport->isActive()) {
                return $airport;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{departureDate: \DateTimeImmutable, returnDate: \DateTimeImmutable}> $windows
     * @param array<string, mixed> $diagnostics
     */
    private function liveHotelSearch(TripSearchRequest $request, array $windows, array &$diagnostics): void
    {
        if ($windows === []) {
            $diagnostics['liveSearch']['hotel'] = ['status' => 'skipped', 'reason' => 'No concrete date windows were available for hotel offer search.'];

            return;
        }

        $cities = [];
        if ($request->destinationCity instanceof City) {
            $cities = [$request->destinationCity];
        } else {
            $country = $request->destinationCountry();
            if ($country instanceof Country) {
                $cities = $this->discoverDestinationCitiesForCountry($country);
                $diagnostics['liveSearch']['hotel']['countryDiscovery'] = [
                    'country' => $country->getName(),
                    'cities' => array_map(static fn (City $city): string => $city->getName(), $cities),
                    'bound' => self::MAX_COUNTRY_DISCOVERY_CITIES,
                ];
            }
        }

        if ($cities === []) {
            $diagnostics['liveSearch']['hotel']['status'] = 'skipped';
            $diagnostics['liveSearch']['hotel']['reason'] = 'No destination city could be resolved for hotel offer search.';

            return;
        }

        $stored = 0;
        $searches = [];
        foreach ($cities as $city) {
            foreach (array_slice($this->hotelRepository->findActiveForTripPlanner($city, $request->hotelStarPreference, self::MAX_HOTELS_FOR_LIVE_SEARCH), 0, self::MAX_HOTELS_FOR_LIVE_SEARCH) as $hotel) {
                foreach ($windows as $window) {
                    try {
                        $hotelRequest = new HotelOfferSearchRequest($window['departureDate'], $window['returnDate'], $request->adults, $request->children, $request->childAges);
                        $summary = $this->hotelSearchService->search($hotel, $hotelRequest);
                        $stored += $this->hotelStoreService->storeSummary($hotel, $hotelRequest, $summary);
                        $searches[] = ['hotel' => $hotel->getName(), 'city' => $city->getName(), 'window' => [$window['departureDate']->format('Y-m-d'), $window['returnDate']->format('Y-m-d')], 'sources' => $this->sourceDiagnostics($summary->getResults()), 'acceptedCount' => \count($summary->getCandidates())];
                    } catch (\Throwable $exception) {
                        $searches[] = ['hotel' => $hotel->getName(), 'city' => $city->getName(), 'window' => [$window['departureDate']->format('Y-m-d'), $window['returnDate']->format('Y-m-d')], 'status' => 'provider_error', 'error' => $exception->getMessage()];
                    }
                }
            }
        }

        $diagnostics['liveSearch']['hotel']['storedCount'] = $stored;
        $diagnostics['liveSearch']['hotel']['searches'] = $searches;
    }

    /**
     * @param ExternalTourOfferCandidate[] $candidates
     *
     * @return array<int, array{departureDate: \DateTimeImmutable, returnDate: \DateTimeImmutable}>
     */
    private function dateWindowsFromTourCandidates(array $candidates, TripSearchRequest $request): array
    {
        $windows = [];
        foreach ($candidates as $candidate) {
            if (!$candidate->departureDate instanceof \DateTimeImmutable) {
                continue;
            }
            $returnDate = $candidate->returnDate ?? ($candidate->nights !== null ? $candidate->departureDate->modify('+' . $candidate->nights . ' days') : null);
            if (!$returnDate instanceof \DateTimeImmutable) {
                continue;
            }
            if ($request->isFlexible()) {
                if (!$request->windowStart instanceof \DateTimeImmutable || !$request->windowEnd instanceof \DateTimeImmutable) {
                    continue;
                }
                if ($candidate->departureDate < $request->windowStart || $returnDate > $request->windowEnd || $candidate->nights !== $request->nights) {
                    continue;
                }
            }
            $key = $candidate->departureDate->format('Y-m-d') . '|' . $returnDate->format('Y-m-d');
            $windows[$key] = ['departureDate' => $candidate->departureDate, 'returnDate' => $returnDate];
            if (\count($windows) >= self::MAX_LIVE_DATE_WINDOWS) {
                break;
            }
        }

        return array_values($windows);
    }

    /**
     * @param array<int, ExternalTourOfferSearchResult|FlightOfferSearchResult|HotelOfferSearchResult> $results
     *
     * @return array<int, array<string, mixed>>
     */
    private function sourceDiagnostics(array $results): array
    {
        return array_map(static fn (ExternalTourOfferSearchResult|FlightOfferSearchResult|HotelOfferSearchResult $result): array => [
            'source' => $result->source->getName(),
            'status' => $result->status->value,
            'rawCount' => (int) ($result->metadata['rawResultCount'] ?? 0),
            'acceptedCount' => \count($result->candidates),
            'rejectedCount' => (int) ($result->metadata['rejectedCandidateCount'] ?? 0),
            'errors' => $result->errors,
            'metadata' => $result->metadata,
        ], $results);
    }

    /**
     * @param \App\Modules\TripPlanner\ValueObject\JourneyOption[] $journeys
     *
     * @return array<int, array<string, mixed>>
     */
    private function journeyDiagnostics(array $journeys): array
    {
        return array_map(static fn ($journey): array => [
            'strategyCode' => $journey->strategyCode,
            'title' => $journey->title,
            'fullyVerified' => $journey->fullyVerified,
            'legs' => array_map(static fn ($leg): array => [
                'mode' => $leg->mode->value,
                'origin' => $leg->origin,
                'destination' => $leg->destination,
                'verifiedInventory' => $leg->verifiedInventory,
                'summary' => $leg->summary,
                'warnings' => $leg->warnings,
            ], $journey->legs),
            'reasons' => $journey->reasons,
            'warnings' => $journey->warnings,
        ], $journeys);
    }

    /**
     * @return array<string, int>
     */
    private function emptyCounts(): array
    {
        return ['tourOptions' => 0, 'customOptions' => 0, 'completeOptions' => 0, 'partialOptions' => 0, 'flightCandidates' => 0, 'hotelCandidates' => 0, 'activityCandidates' => 0, 'transferCandidates' => 0];
    }
}
