<?php

namespace App\Modules\TripPlanner\Service;

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

        $dateWindows = $this->liveTourSearch($request, $diagnostics);
        $request = $request->withCandidateDateWindows($dateWindows);
        $this->liveFlightSearch($request, $diagnostics);
        $this->liveHotelSearch($request, $dateWindows, $diagnostics);

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
                    ? 'No configured tour source exposes a supported destination city in the selected country.'
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

        $cities = $this->configuredTourCitiesForCountry($country);
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
     * @param array<string, mixed> $diagnostics
     */
    private function liveFlightSearch(TripSearchRequest $request, array &$diagnostics): void
    {
        if (!$request->originAirport || !$request->destinationCity instanceof City) {
            $diagnostics['liveSearch']['flight'] = ['status' => 'skipped', 'reason' => 'Origin airport and destination city are required for external flight search.'];

            return;
        }

        $destinationAirport = $request->destinationCity->getAirports()->first();
        if (!$destinationAirport instanceof \App\Modules\Destination\Entity\Airport) {
            $diagnostics['liveSearch']['flight'] = ['status' => 'skipped', 'reason' => 'Destination city has no airport for external flight search.'];

            return;
        }

        $stored = 0;
        $summaries = [];
        foreach ($this->dateWindows($request) as $window) {
            try {
                $flightRequest = new FlightOfferSearchRequest($request->originAirport, $destinationAirport, $window['departureDate'], $window['returnDate'], $request->adults, $request->children, $request->infants, $request->flightCabin ?? FlightCabinClass::ECONOMY, $request->directFlightPreferred ? true : null);
                $summary = $this->flightSearchService->search($flightRequest);
                $stored += $this->flightStoreService->storeSummary($flightRequest, $summary);
                $summaries[] = ['window' => [$window['departureDate']->format('Y-m-d'), $window['returnDate']->format('Y-m-d')], 'sources' => $this->sourceDiagnostics($summary->getResults()), 'acceptedCount' => \count($summary->getCandidates())];
            } catch (\Throwable $exception) {
                $summaries[] = ['window' => [$window['departureDate']->format('Y-m-d'), $window['returnDate']->format('Y-m-d')], 'status' => 'provider_error', 'error' => $exception->getMessage()];
            }
        }

        $diagnostics['liveSearch']['flight'] = ['storedCount' => $stored, 'searches' => $summaries];
    }

    /**
     * @param array<int, array{departureDate: \DateTimeImmutable, returnDate: \DateTimeImmutable}> $dateWindows
     * @param array<string, mixed> $diagnostics
     */
    private function liveHotelSearch(TripSearchRequest $request, array $dateWindows, array &$diagnostics): void
    {
        if (!$request->destinationCity instanceof City) {
            $diagnostics['liveSearch']['hotel'] = ['status' => 'skipped', 'reason' => 'Destination city is required for hotel offer search.'];

            return;
        }

        $windows = $request->isFlexible() ? array_slice($dateWindows, 0, self::MAX_LIVE_DATE_WINDOWS) : $this->dateWindows($request);
        if ($windows === []) {
            $diagnostics['liveSearch']['hotel'] = ['status' => 'skipped', 'reason' => 'No concrete date windows were available for hotel offer search.'];

            return;
        }

        $stored = 0;
        $searches = [];
        foreach (array_slice($this->hotelRepository->findActiveForTripPlanner($request->destinationCity, $request->hotelStarPreference, self::MAX_HOTELS_FOR_LIVE_SEARCH), 0, self::MAX_HOTELS_FOR_LIVE_SEARCH) as $hotel) {
            foreach ($windows as $window) {
                try {
                    $hotelRequest = new HotelOfferSearchRequest($window['departureDate'], $window['returnDate'], $request->adults, $request->children, $request->childAges);
                    $summary = $this->hotelSearchService->search($hotel, $hotelRequest);
                    $stored += $this->hotelStoreService->storeSummary($hotel, $hotelRequest, $summary);
                    $searches[] = ['hotel' => $hotel->getName(), 'window' => [$window['departureDate']->format('Y-m-d'), $window['returnDate']->format('Y-m-d')], 'sources' => $this->sourceDiagnostics($summary->getResults()), 'acceptedCount' => \count($summary->getCandidates())];
                } catch (\Throwable $exception) {
                    $searches[] = ['hotel' => $hotel->getName(), 'window' => [$window['departureDate']->format('Y-m-d'), $window['returnDate']->format('Y-m-d')], 'status' => 'provider_error', 'error' => $exception->getMessage()];
                }
            }
        }

        $diagnostics['liveSearch']['hotel'] = ['storedCount' => $stored, 'searches' => $searches];
    }

    /**
     * @return array<int, array{departureDate: \DateTimeImmutable, returnDate: \DateTimeImmutable}>
     */
    private function dateWindows(TripSearchRequest $request): array
    {
        if (!$request->isFlexible()) {
            if ($request->departureDate instanceof \DateTimeImmutable && $request->returnDate instanceof \DateTimeImmutable) {
                return [['departureDate' => $request->departureDate, 'returnDate' => $request->returnDate]];
            }

            return [];
        }

        return array_slice($request->candidateDateWindows, 0, self::MAX_LIVE_DATE_WINDOWS);
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
