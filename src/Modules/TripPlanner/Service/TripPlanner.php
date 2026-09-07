<?php

namespace App\Modules\TripPlanner\Service;

use App\Modules\Activity\Enum\ActivityCategory;
use App\Modules\Activity\Service\ActivityOfferResolver;
use App\Modules\Activity\ValueObject\ActivityPricingCandidate;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\Flight\Enum\FlightPriceSourceType;
use App\Modules\Flight\Service\FlightPricingResolver;
use App\Modules\Flight\ValueObject\FlightPricingCandidate;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Enum\HotelPriceSourceType;
use App\Modules\Hotel\Repository\HotelRepository;
use App\Modules\Hotel\Service\HotelPricingResolver;
use App\Modules\Hotel\ValueObject\HotelPricingCandidate;
use App\Modules\Tour\Enum\TourOfferSourceType;
use App\Modules\Tour\Service\TourOfferResolver;
use App\Modules\Tour\ValueObject\TourPricingCandidate;
use App\Modules\Transfer\Service\TransferOfferResolver;
use App\Modules\Transfer\ValueObject\TransferEndpointContext;
use App\Modules\Transfer\ValueObject\TransferPricingCandidate;
use App\Modules\TripPlanner\Enum\TripOptionType;
use App\Modules\TripPlanner\Enum\TripPlanStatus;
use App\Modules\TripPlanner\ValueObject\TripComponentSummary;
use App\Modules\TripPlanner\ValueObject\TripMoney;
use App\Modules\TripPlanner\ValueObject\TripOption;
use App\Modules\TripPlanner\ValueObject\TripPlanResult;
use App\Modules\TripPlanner\ValueObject\TripSearchRequest;

final readonly class TripPlanner
{
    private const MAX_NORMALIZED_OPTIONS = 10;
    private const MAX_RECOMMENDATIONS = 5;
    private const MAX_TOUR_OPTIONS = 10;
    private const MAX_FLIGHT_CANDIDATES = 3;
    private const MAX_HOTELS_TO_PRICE = 8;
    private const MAX_HOTEL_CANDIDATES = 3;
    private const MAX_CUSTOM_OPTIONS = 10;
    private const MAX_ACTIVITIES = 3;

    public function __construct(
        private TourOfferResolver $tourOfferResolver,
        private FlightPricingResolver $flightPricingResolver,
        private HotelRepository $hotelRepository,
        private HotelPricingResolver $hotelPricingResolver,
        private ActivityOfferResolver $activityOfferResolver,
        private TransferOfferResolver $transferOfferResolver,
        private HotelRecommendationContextBuilder $hotelContextBuilder,
    ) {
    }

    public function plan(TripSearchRequest $request): TripPlanResult
    {
        $messages = [];
        $warnings = [];
        $diagnostics = [];

        $tourOptions = $this->tourOptions($request, $messages, $diagnostics);
        $flightCandidates = $this->flightCandidates($request, $messages, $diagnostics);
        $hotelCandidates = $this->hotelCandidates($request, $messages, $diagnostics);
        $customOptions = $this->customOptions($request, $flightCandidates, $hotelCandidates, $messages, $warnings, $diagnostics);

        $options = $this->deduplicate(array_merge($tourOptions, $customOptions));
        usort($options, $this->compareOptions(...));
        $options = array_slice($options, 0, self::MAX_RECOMMENDATIONS);
        $options = $this->hotelContextBuilder->enrichShortlist($options, $diagnostics);
        $options = array_map(static fn (TripOption $option, int $index): TripOption => $option->withRank($index + 1), $options, array_keys($options));

        $completeOptions = array_values(array_filter($options, static fn (TripOption $option): bool => $option->isComplete()));
        $counts = [
            'tourOptions' => \count($tourOptions),
            'customOptions' => \count($customOptions),
            'completeOptions' => \count($completeOptions),
            'partialOptions' => \count($options) - \count($completeOptions),
            'flightCandidates' => \count($flightCandidates),
            'hotelCandidates' => \count($hotelCandidates),
            'activityCandidates' => (int) ($diagnostics['activityCandidates'] ?? 0),
            'transferCandidates' => (int) ($diagnostics['transferCandidates'] ?? 0),
            'deduplicatedOptions' => \count($options),
            'shortlistedRecommendations' => \count($options),
        ];

        if ($options === []) {
            $messages[] = 'No trip options could be built from the currently stored commercial data.';
            if ($counts['tourOptions'] === 0) {
                $messages[] = $request->isFlexible()
                    ? 'No ' . $request->nightsOrDerived() . '-night option was found inside the selected date window.'
                    : 'No matching tour package was found.';
            }
            if ($request->originAirport !== null && $counts['flightCandidates'] === 0) {
                $messages[] = 'No suitable flight was available for the requested dates.';
            }
            if ($counts['hotelCandidates'] === 0) {
                $messages[] = 'No priced hotel option was available for the destination and dates.';
            }
            if (!$request->hasDestinationScope()) {
                $messages[] = 'No destination was selected. Broad discovery can use configured sources only when they support destination-free search.';
            }

            return new TripPlanResult(TripPlanStatus::NO_OPTIONS, [], array_values(array_unique($messages)), $counts, $diagnostics);
        }

        foreach ($warnings as $warning) {
            $messages[] = $warning;
        }

        $status = \count($completeOptions) > 0 ? TripPlanStatus::OPTIONS_FOUND : TripPlanStatus::PARTIAL_OPTIONS;
        $messages[] = $status === TripPlanStatus::OPTIONS_FOUND
            ? 'Trip options were built from available commercial inventory.'
            : 'Only partial trip options could be built from available commercial inventory.';

        return new TripPlanResult($status, $options, array_values(array_unique($messages)), $counts, $diagnostics);
    }

    /**
     * @param string[] $messages
     * @param array<string, mixed> $diagnostics
     *
     * @return TripOption[]
     */
    private function tourOptions(TripSearchRequest $request, array &$messages, array &$diagnostics): array
    {
        if (!$request->hasDestinationScope()) {
            $messages[] = 'Tour search was skipped because no destination city or country was selected.';

            return [];
        }

        try {
            $candidates = $this->tourOfferResolver->resolveForDestination(
                $request->originAirport,
                $request->destinationCountry(),
                $request->destinationCity,
                $request->isFlexible() ? null : $request->departureDate,
                $request->isFlexible() ? null : $request->returnDate,
                $request->isFlexible() ? $request->windowStart : null,
                $request->isFlexible() ? $request->windowEnd : null,
                $request->nights,
                $request->adults,
                $request->children,
                $request->infants,
                $request->childAges,
                $request->rooms,
            );
        } catch (\Throwable $exception) {
            $messages[] = 'Tour resolver failed; tour options are unavailable for this search.';
            $diagnostics['tourResolverError'] = $exception->getMessage();

            return [];
        }

        $options = [];
        foreach (array_slice($candidates, 0, self::MAX_TOUR_OPTIONS) as $candidate) {
            $options[] = $this->tourOption($request, $candidate);
        }

        if ($options === []) {
            $messages[] = $request->isFlexible()
                ? 'No ' . $request->nightsOrDerived() . '-night option was found inside the selected destination/date window.'
                : 'No matching tour package was found.';
        }

        return $options;
    }

    private function tourOption(TripSearchRequest $request, TourPricingCandidate $candidate): TripOption
    {
        $isOwn = $candidate->sourceType === TourOfferSourceType::OWN;
        $budgetStatus = $this->budgetStatus($request, $candidate->currency, $candidate->totalPrice);
        $reasons = [$isOwn ? 'Own tour package' : 'External tour offer'];
        $warnings = [];
        if ($request->isFlexible()) {
            $reasons[] = $candidate->nights !== null
                ? $candidate->nights . '-night option within your requested date window'
                : 'Option within your requested date window';
            $reasons[] = 'Lower-priced matching departure in the selected period';
        }
        if ($budgetStatus === 'within_budget') {
            $reasons[] = 'Within your budget';
        } elseif ($budgetStatus === 'above_budget') {
            $warnings[] = 'Above budget';
        }

        $score = $this->score(true, $isOwn, $budgetStatus, false, false, \count($candidate->inclusions));
        $sourceName = $isOwn ? 'OUR Tour Package' : ($candidate->externalTourOffer?->getSearchSource()?->getName() ?? 'External Tour');
        $hotelContext = $this->hotelContextBuilder->fromTourCandidate($candidate);
        $externalMetadata = $candidate->externalTourOffer?->getMetadata() ?? [];
        $destinationCity = $candidate->tourPackage?->getDestinationCity() ?? $candidate->externalTourOffer?->getDestinationCity();
        $destinationCountry = $destinationCity?->getCountry();
        $sourceType = 'own';
        if (!$isOwn) {
            $sourceType = $this->isLiveExternalTour($candidate) ? 'live_external' : 'cached_external';
            $reasons[] = $sourceType === 'live_external'
                ? 'Fresh live external result'
                : 'Cached external fallback';
            if ($sourceType === 'cached_external' && $candidate->externalTourOffer?->getFetchedAt() instanceof \DateTimeImmutable) {
                $warnings[] = 'External offer is cached from ' . $candidate->externalTourOffer->getFetchedAt()->format('Y-m-d H:i') . '.';
            }
        }

        return new TripOption(
            optionType: $isOwn ? TripOptionType::OUR_TOUR : TripOptionType::EXTERNAL_TOUR,
            sourceType: $sourceType,
            sourceName: $sourceName,
            title: $candidate->title,
            currency: $candidate->currency,
            totalPrice: $candidate->totalPrice,
            components: [
                new TripComponentSummary('tour', $candidate->title, $candidate->sourceType->value, $sourceName, $candidate->currency, $candidate->totalPrice, $candidate->hotelSummary ?: $candidate->destination, $candidate->bookingUrl),
            ],
            departureDate: $this->candidateDepartureDate($request, $candidate),
            returnDate: $this->candidateReturnDate($request, $candidate),
            nights: $candidate->nights ?? $request->nightsOrDerived(),
            completenessStatus: 'complete',
            budgetStatus: $budgetStatus,
            rankingScore: $score,
            reasons: $reasons,
            warnings: $warnings,
            bookingUrl: $candidate->bookingUrl,
            destinationCountry: $destinationCountry?->getName(),
            destinationCity: $destinationCity?->getName() ?? $candidate->destination,
            hotelName: $hotelContext?->hotelName,
            hotelGrade: $hotelContext?->stars !== null ? (string) $hotelContext->stars : null,
            board: $candidate->externalTourOffer?->getBoardType() ?? $candidate->tourPackage?->getBoardType(),
            airline: $candidate->flightSummary,
            agency: \is_scalar($externalMetadata['agency'] ?? null) ? (string) $externalMetadata['agency'] : null,
            hotelRecommendationContext: $hotelContext,
        );
    }

    /**
     * @param string[] $messages
     * @param array<string, mixed> $diagnostics
     *
     * @return FlightPricingCandidate[]
     */
    private function flightCandidates(TripSearchRequest $request, array &$messages, array &$diagnostics): array
    {
        if (!$request->originAirport instanceof \App\Modules\Destination\Entity\Airport) {
            $messages[] = 'No origin airport was selected, so flight pricing was skipped.';

            return [];
        }
        if ($request->destinationCity === null) {
            $messages[] = 'No destination was selected, so flight pricing was skipped.';

            return [];
        }

        try {
            $dateWindows = $this->dateWindowsForCustomSearch($request);
            if ($dateWindows === []) {
                $messages[] = 'No concrete date windows were available for flexible flight pricing.';

                return [];
            }

            $candidates = [];
            foreach ($dateWindows as $window) {
                foreach ($this->flightPricingResolver->resolve(
                    $request->originAirport,
                    $this->destinationAirport($request),
                    $window['departureDate'],
                    $window['returnDate'],
                    $request->adults,
                    $request->children,
                    $request->infants,
                    $request->flightCabin ?? FlightCabinClass::ECONOMY,
                    $request->directFlightPreferred ? true : null,
                ) as $candidate) {
                    $candidates[] = $candidate;
                    if (\count($candidates) >= self::MAX_FLIGHT_CANDIDATES) {
                        return $candidates;
                    }
                }
            }

            // Direct origin produced nothing priced yet — only now consider the
            // validated alternative departure airport TravelPlanningService found.
            if ($candidates === [] && $request->commercialDepartureAirport instanceof \App\Modules\Destination\Entity\Airport) {
                foreach ($dateWindows as $window) {
                    foreach ($this->flightPricingResolver->resolve(
                        $request->commercialDepartureAirport,
                        $this->destinationAirport($request),
                        $window['departureDate'],
                        $window['returnDate'],
                        $request->adults,
                        $request->children,
                        $request->infants,
                        $request->flightCabin ?? FlightCabinClass::ECONOMY,
                        $request->directFlightPreferred ? true : null,
                    ) as $candidate) {
                        $candidates[] = $candidate;
                        if (\count($candidates) >= self::MAX_FLIGHT_CANDIDATES) {
                            return $candidates;
                        }
                    }
                }
            }

            return $candidates;
        } catch (\Throwable $exception) {
            $messages[] = 'No suitable flight was available for the requested dates.';
            $diagnostics['flightResolverError'] = $exception->getMessage();

            return [];
        }
    }

    private function destinationAirport(TripSearchRequest $request): \App\Modules\Destination\Entity\Airport
    {
        if ($request->destinationCity === null) {
            throw new \LogicException('Destination city is required for flight pricing.');
        }
        $airports = $request->destinationCity->getAirports();
        $airport = $airports->first();
        if (!$airport instanceof \App\Modules\Destination\Entity\Airport) {
            throw new \LogicException('Destination city has no airport available for flight pricing.');
        }

        return $airport;
    }

    /**
     * @param string[] $messages
     * @param array<string, mixed> $diagnostics
     *
     * @return array<int, array{hotel: Hotel, candidate: HotelPricingCandidate, departureDate: \DateTimeImmutable, returnDate: \DateTimeImmutable}>
     */
    private function hotelCandidates(TripSearchRequest $request, array &$messages, array &$diagnostics): array
    {
        if ($request->destinationCity === null) {
            $messages[] = 'No destination was selected, so hotel pricing was skipped.';

            return [];
        }
        $dateWindows = $this->dateWindowsForCustomSearch($request);
        if ($dateWindows === []) {
            $messages[] = 'No concrete date windows were available for hotel pricing.';

            return [];
        }

        $priced = [];
        $hotels = $this->hotelRepository->findActiveForTripPlanner($request->destinationCity, $request->hotelStarPreference, self::MAX_HOTELS_TO_PRICE);
        foreach ($hotels as $hotel) {
            foreach ($dateWindows as $window) {
                try {
                    foreach ($this->hotelPricingResolver->resolve($hotel, $window['departureDate'], $window['returnDate'], $request->adults, $request->children, $request->childAges) as $candidate) {
                        $priced[] = ['hotel' => $hotel, 'candidate' => $candidate, 'departureDate' => $window['departureDate'], 'returnDate' => $window['returnDate']];
                        break 2;
                    }
                } catch (\Throwable $exception) {
                    $diagnostics['hotelResolverErrors'][] = $hotel->getName() . ': ' . $exception->getMessage();
                }
            }

            if (\count($priced) >= self::MAX_HOTEL_CANDIDATES) {
                break;
            }
        }

        if ($priced === []) {
            $messages[] = 'No priced hotel option was available for the destination and dates.';
        }

        return $priced;
    }

    /**
     * @param string[] $messages
     * @param array<string, mixed> $diagnostics
     *
     * @return ActivityPricingCandidate[]
     */
    private function activityCandidates(TripSearchRequest $request, \DateTimeImmutable $travelDate, array &$messages, array &$diagnostics): array
    {
        if ($request->destinationCity === null) {
            return [];
        }

        $categories = $request->activityCategories === [] ? [null] : array_values(array_filter(array_map(
            static fn (string $value): ?ActivityCategory => ActivityCategory::tryFrom($value),
            $request->activityCategories,
        )));

        $candidates = [];
        foreach ($categories as $category) {
            try {
                foreach ($this->activityOfferResolver->resolve($request->destinationCity, $travelDate, $request->adults, $request->children, $request->infants, $category) as $candidate) {
                    $key = (string) $candidate->activity->getId();
                    $candidates[$key] ??= $candidate;
                    if (\count($candidates) >= self::MAX_ACTIVITIES) {
                        break 2;
                    }
                }
            } catch (\Throwable $exception) {
                $diagnostics['activityResolverErrors'][] = $exception->getMessage();
            }
        }

        if ($request->activityCategories !== [] && $candidates === []) {
            $messages[] = 'No matching activity was found for the selected interests.';
        }

        return array_values($candidates);
    }

    /**
     * @param FlightPricingCandidate[] $flights
     * @param array<int, array{hotel: Hotel, candidate: HotelPricingCandidate, departureDate: \DateTimeImmutable, returnDate: \DateTimeImmutable}> $hotels
     * @param string[] $messages
     * @param string[] $warnings
     * @param array<string, mixed> $diagnostics
     *
     * @return TripOption[]
     */
    private function customOptions(TripSearchRequest $request, array $flights, array $hotels, array &$messages, array &$warnings, array &$diagnostics): array
    {
        if ($flights === [] || $hotels === []) {
            return [];
        }

        $options = [];
        foreach ($flights as $flight) {
            foreach ($hotels as $hotelRow) {
                $hotel = $hotelRow['hotel'];
                $hotelCandidate = $hotelRow['candidate'];
                $departureDate = $this->flightDepartureDate($flight) ?? $hotelRow['departureDate'];
                $returnDate = $this->flightReturnDate($flight) ?? $hotelRow['returnDate'];
                if ($departureDate->format('Y-m-d') !== $hotelRow['departureDate']->format('Y-m-d') || $returnDate->format('Y-m-d') !== $hotelRow['returnDate']->format('Y-m-d')) {
                    continue;
                }
                $transfer = $this->transferCandidate($request, $hotel, $departureDate, $diagnostics);
                $activities = $this->activityCandidates($request, $departureDate, $messages, $diagnostics);
                $optionWarnings = [];
                $components = [
                    new TripComponentSummary('flight', $flight->routeSummary, $flight->sourceType->value, null, $flight->currency, $flight->totalPrice, $flight->airlineSummary . ' / ' . $flight->departureSummary),
                    new TripComponentSummary('hotel', $hotel->getName(), $hotelCandidate->sourceType->value, null, $hotelCandidate->currency, $hotelCandidate->totalPrice, $hotelCandidate->roomName),
                ];

                $flightOriginAirport = $this->flightOriginAirport($flight);
                if ($flightOriginAirport !== null && $request->originAirport !== null && $flightOriginAirport->getId() !== $request->originAirport->getId()) {
                    $commercialDepartureCity = $flightOriginAirport->getCity()?->getName() ?? $flightOriginAirport->getName();
                    $userOriginCity = $request->originCity?->getName() ?? $request->originAirport->getName();
                    $optionWarnings[] = sprintf('This option departs from %s, not your own origin (%s). You would need to reach %s first.', $commercialDepartureCity, $userOriginCity, $commercialDepartureCity);
                }

                if ($request->transferRequired && !$transfer instanceof TransferPricingCandidate) {
                    $optionWarnings[] = 'Requested transfer is not currently available.';
                    $warnings[] = 'Transfer was requested but no matching transfer is configured.';
                } elseif ($transfer instanceof TransferPricingCandidate) {
                    $components[] = new TripComponentSummary('transfer', $transfer->transferProduct->getName(), $transfer->sourceType->value, null, $transfer->currency, $transfer->totalPrice, $transfer->transferProduct->getOriginLabel() . ' -> ' . $transfer->transferProduct->getDestinationLabel());
                }

                foreach ($activities as $activity) {
                    $components[] = new TripComponentSummary('activity', $activity->activity->getName(), $activity->sourceType->value, null, $activity->currency, $activity->totalPrice, $activity->activity->getCategory()->value);
                }

                [$currency, $total, $currencyWarning] = $this->sumComponents($components);
                if ($currencyWarning !== null) {
                    $optionWarnings[] = $currencyWarning;
                }

                $complete = $optionWarnings === [] || ($optionWarnings === ['No matching activity was found for the selected interests.']);
                $budgetStatus = $currency !== null && $total !== null ? $this->budgetStatus($request, $currency, $total) : 'unknown';
                $reasons = ['Custom flight + hotel option'];
                if ($flight->sourceType === FlightPriceSourceType::OWN || $hotelCandidate->sourceType === HotelPriceSourceType::OWN) {
                    $reasons[] = 'Includes own inventory';
                }
                if ($budgetStatus === 'within_budget') {
                    $reasons[] = 'Within your budget';
                } elseif ($budgetStatus === 'above_budget') {
                    $optionWarnings[] = 'Above budget';
                }
                if ($transfer instanceof TransferPricingCandidate) {
                    $reasons[] = 'Includes requested transfer';
                }
                if ($activities !== []) {
                    $reasons[] = 'Includes matching activities';
                }

                $options[] = new TripOption(
                    TripOptionType::CUSTOM_COMBINATION,
                    'mixed',
                    null,
                    'Custom trip: ' . $flight->routeSummary . ' + ' . $hotel->getName(),
                    $currency,
                    $total,
                    $components,
                    $departureDate,
                    $returnDate,
                    max(1, (int) $departureDate->diff($returnDate)->format('%a')),
                    $complete ? 'complete' : 'partial',
                    $budgetStatus,
                    $this->score($complete, false, $budgetStatus, $request->transferRequired && $transfer instanceof TransferPricingCandidate, $activities !== [], \count($activities), $flight->sourceType === FlightPriceSourceType::OWN || $hotelCandidate->sourceType === HotelPriceSourceType::OWN),
                    $reasons,
                    array_values(array_unique($optionWarnings)),
                    destinationCountry: $request->destinationCountry()?->getName(),
                    destinationCity: $request->destinationCity?->getName(),
                    hotelName: $hotel->getName(),
                    hotelGrade: $hotel->getStars() !== null ? (string) $hotel->getStars() : null,
                );

                if (\count($options) >= self::MAX_CUSTOM_OPTIONS) {
                    return $options;
                }
            }
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $diagnostics
     */
    private function transferCandidate(TripSearchRequest $request, Hotel $hotel, \DateTimeImmutable $travelDate, array &$diagnostics): ?TransferPricingCandidate
    {
        if (!$request->transferRequired) {
            return null;
        }
        if ($request->destinationCity === null) {
            return null;
        }

        try {
            $candidates = $this->transferOfferResolver->resolve(
                TransferEndpointContext::forCity($request->destinationCity),
                TransferEndpointContext::forHotel($hotel),
                $travelDate,
                $request->passengerCount(),
            );
            $diagnostics['transferCandidates'] = max((int) ($diagnostics['transferCandidates'] ?? 0), \count($candidates));

            return $candidates[0] ?? null;
        } catch (\Throwable $exception) {
            $diagnostics['transferResolverError'] = $exception->getMessage();

            return null;
        }
    }

    /**
     * @param TripComponentSummary[] $components
     *
     * @return array{0: string|null, 1: string|null, 2: string|null}
     */
    private function sumComponents(array $components): array
    {
        $currencies = array_values(array_unique(array_filter(array_map(static fn (TripComponentSummary $component): ?string => $component->currency, $components))));
        $prices = array_values(array_filter(array_map(static fn (TripComponentSummary $component): ?string => $component->price, $components)));

        if (\count($currencies) !== 1 || \count($prices) !== \count($components)) {
            return [null, null, 'Price cannot be directly compared because currencies differ or one component has no current price.'];
        }

        return [$currencies[0], TripMoney::sum($prices), null];
    }

    private function budgetStatus(TripSearchRequest $request, string $currency, string $price): string
    {
        if ($request->budget === null) {
            return 'unknown';
        }
        if ($currency !== $request->preferredCurrency) {
            return 'unknown';
        }

        return TripMoney::cents($price) <= TripMoney::cents($request->budget) ? 'within_budget' : 'above_budget';
    }

    private function score(bool $complete, bool $ownTour, string $budgetStatus, bool $transferMatched = false, bool $activitiesMatched = false, int $activityCount = 0, bool $ownComponent = false): int
    {
        $score = $complete ? 1000 : 500;
        if ($ownTour) {
            $score += 500;
        } elseif ($ownComponent) {
            $score += 150;
        }
        if ($budgetStatus === 'within_budget') {
            $score += 100;
        } elseif ($budgetStatus === 'above_budget') {
            $score -= 50;
        }
        if ($transferMatched) {
            $score += 50;
        }
        if ($activitiesMatched) {
            $score += min(self::MAX_ACTIVITIES, $activityCount) * 10;
        }

        return $score;
    }

    private function compareOptions(TripOption $left, TripOption $right): int
    {
        if ($left->rankingScore !== $right->rankingScore) {
            return $right->rankingScore <=> $left->rankingScore;
        }

        $typeRank = static fn (TripOption $option): int => match ($option->optionType) {
            TripOptionType::OUR_TOUR => 0,
            TripOptionType::CUSTOM_COMBINATION => 1,
            TripOptionType::EXTERNAL_TOUR => 2,
        };
        if ($typeRank($left) !== $typeRank($right)) {
            return $typeRank($left) <=> $typeRank($right);
        }

        if ($left->currency !== null && $left->currency === $right->currency && $left->totalPrice !== null && $right->totalPrice !== null) {
            return TripMoney::cents($left->totalPrice) <=> TripMoney::cents($right->totalPrice);
        }

        return $left->title <=> $right->title;
    }

    /**
     * @param TripOption[] $options
     *
     * @return TripOption[]
     */
    private function deduplicate(array $options): array
    {
        $unique = [];
        foreach (array_slice($options, 0, self::MAX_NORMALIZED_OPTIONS * 2) as $option) {
            $key = implode('|', [
                $option->optionType->value,
                $option->sourceName ?? $option->sourceType,
                $this->normalize($option->title),
                $option->departureDate->format('Y-m-d'),
                $option->returnDate?->format('Y-m-d') ?? '',
                $this->normalize($option->destinationCity ?? ''),
                $this->normalize($option->hotelName ?? ''),
                $this->normalize($option->airline ?? ''),
                $this->normalize($option->agency ?? ''),
                $option->currency ?? '',
                $option->totalPrice ?? '',
            ]);
            $unique[$key] ??= $option;
            if (\count($unique) >= self::MAX_NORMALIZED_OPTIONS) {
                break;
            }
        }

        return array_values($unique);
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower($value)));
    }

    /**
     * @return array<int, array{departureDate: \DateTimeImmutable, returnDate: \DateTimeImmutable}>
     */
    private function dateWindowsForCustomSearch(TripSearchRequest $request): array
    {
        if (!$request->isFlexible()) {
            if (!$request->departureDate instanceof \DateTimeImmutable || !$request->returnDate instanceof \DateTimeImmutable) {
                return [];
            }

            return [['departureDate' => $request->departureDate, 'returnDate' => $request->returnDate]];
        }

        return array_slice($request->candidateDateWindows, 0, 3);
    }

    private function flightDepartureDate(FlightPricingCandidate $candidate): ?\DateTimeImmutable
    {
        $outbound = $candidate->flightOffer?->getOrderedLegs(\App\Modules\Flight\Enum\FlightDirection::OUTBOUND);
        $first = $outbound[0] ?? null;

        return $first?->getDepartureAt();
    }

    private function flightOriginAirport(FlightPricingCandidate $candidate): ?\App\Modules\Destination\Entity\Airport
    {
        $outbound = $candidate->flightOffer?->getOrderedLegs(\App\Modules\Flight\Enum\FlightDirection::OUTBOUND);
        $first = $outbound[0] ?? null;

        return $first?->getOriginAirport();
    }

    private function flightReturnDate(FlightPricingCandidate $candidate): ?\DateTimeImmutable
    {
        $inbound = $candidate->flightOffer?->getOrderedLegs(\App\Modules\Flight\Enum\FlightDirection::INBOUND);
        $first = $inbound[0] ?? null;

        return $first?->getDepartureAt();
    }

    private function isLiveExternalTour(TourPricingCandidate $candidate): bool
    {
        $fetchedAt = $candidate->externalTourOffer?->getFetchedAt();
        if (!$fetchedAt instanceof \DateTimeImmutable) {
            return false;
        }

        return $fetchedAt >= new \DateTimeImmutable('-2 minutes');
    }

    private function candidateDepartureDate(TripSearchRequest $request, TourPricingCandidate $candidate): \DateTimeImmutable
    {
        return $candidate->tourPackage?->getDepartureDate()
            ?? $candidate->externalTourOffer?->getDepartureDate()
            ?? $request->travelStartDate();
    }

    private function candidateReturnDate(TripSearchRequest $request, TourPricingCandidate $candidate): \DateTimeImmutable
    {
        $departureDate = $this->candidateDepartureDate($request, $candidate);

        return $candidate->tourPackage?->getReturnDate()
            ?? $candidate->externalTourOffer?->getReturnDate()
            ?? $request->returnDate
            ?? $departureDate->modify('+' . $request->nightsOrDerived() . ' days');
    }
}
