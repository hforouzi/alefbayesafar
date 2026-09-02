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
    private const MAX_TOUR_OPTIONS = 8;
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
        $activityCandidates = $this->activityCandidates($request, $messages, $diagnostics);
        $customOptions = $this->customOptions($request, $flightCandidates, $hotelCandidates, $activityCandidates, $messages, $warnings, $diagnostics);

        $options = array_merge($tourOptions, $customOptions);
        usort($options, $this->compareOptions(...));

        $completeOptions = array_values(array_filter($options, static fn (TripOption $option): bool => $option->isComplete()));
        $counts = [
            'tourOptions' => \count($tourOptions),
            'customOptions' => \count($customOptions),
            'completeOptions' => \count($completeOptions),
            'partialOptions' => \count($options) - \count($completeOptions),
            'flightCandidates' => \count($flightCandidates),
            'hotelCandidates' => \count($hotelCandidates),
            'activityCandidates' => \count($activityCandidates),
            'transferCandidates' => (int) ($diagnostics['transferCandidates'] ?? 0),
        ];

        if ($options === []) {
            $messages[] = 'No trip options could be built from the currently stored commercial data.';
            if ($counts['tourOptions'] === 0) {
                $messages[] = 'No matching tour package was found.';
            }
            if ($request->originAirport !== null && $counts['flightCandidates'] === 0) {
                $messages[] = 'No suitable flight was available for the requested dates.';
            }
            if ($counts['hotelCandidates'] === 0) {
                $messages[] = 'No priced hotel option was available for the destination and dates.';
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
        try {
            $candidates = $this->tourOfferResolver->resolve(
                $request->originAirport,
                $request->destinationCity,
                $request->departureDate,
                $request->returnDate,
                null,
                null,
                $request->nights,
                $request->adults,
                $request->children,
                $request->infants,
                $request->childAges,
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
            $messages[] = 'No matching tour package was found.';
        }

        return $options;
    }

    private function tourOption(TripSearchRequest $request, TourPricingCandidate $candidate): TripOption
    {
        $isOwn = $candidate->sourceType === TourOfferSourceType::OWN;
        $budgetStatus = $this->budgetStatus($request, $candidate->currency, $candidate->totalPrice);
        $reasons = [$isOwn ? 'Own tour package' : 'External tour offer'];
        $warnings = [];
        if ($budgetStatus === 'within_budget') {
            $reasons[] = 'Within your budget';
        } elseif ($budgetStatus === 'above_budget') {
            $warnings[] = 'Above budget';
        }

        $score = $this->score(true, $isOwn, $budgetStatus, false, false, \count($candidate->inclusions));
        $sourceName = $isOwn ? 'OUR Tour Package' : ($candidate->externalTourOffer?->getSearchSource()?->getName() ?? 'External Tour');

        return new TripOption(
            optionType: $isOwn ? TripOptionType::OUR_TOUR : TripOptionType::EXTERNAL_TOUR,
            sourceType: $isOwn ? 'own' : 'external',
            sourceName: $sourceName,
            title: $candidate->title,
            currency: $candidate->currency,
            totalPrice: $candidate->totalPrice,
            components: [
                new TripComponentSummary('tour', $candidate->title, $candidate->sourceType->value, $sourceName, $candidate->currency, $candidate->totalPrice, $candidate->hotelSummary ?: $candidate->destination, $candidate->bookingUrl),
            ],
            departureDate: $request->departureDate,
            returnDate: $request->returnDate,
            nights: $candidate->nights ?? $request->nightsOrDerived(),
            completenessStatus: 'complete',
            budgetStatus: $budgetStatus,
            rankingScore: $score,
            reasons: $reasons,
            warnings: $warnings,
            bookingUrl: $candidate->bookingUrl,
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

        try {
            return array_slice($this->flightPricingResolver->resolve(
                $request->originAirport,
                $this->destinationAirport($request),
                $request->departureDate,
                $request->returnDate,
                $request->adults,
                $request->children,
                $request->infants,
                $request->flightCabin ?? FlightCabinClass::ECONOMY,
                $request->directFlightPreferred ? true : null,
            ), 0, self::MAX_FLIGHT_CANDIDATES);
        } catch (\Throwable $exception) {
            $messages[] = 'No suitable flight was available for the requested dates.';
            $diagnostics['flightResolverError'] = $exception->getMessage();

            return [];
        }
    }

    private function destinationAirport(TripSearchRequest $request): \App\Modules\Destination\Entity\Airport
    {
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
     * @return array<int, array{hotel: Hotel, candidate: HotelPricingCandidate}>
     */
    private function hotelCandidates(TripSearchRequest $request, array &$messages, array &$diagnostics): array
    {
        $priced = [];
        $hotels = $this->hotelRepository->findActiveForTripPlanner($request->destinationCity, $request->hotelStarPreference, self::MAX_HOTELS_TO_PRICE);
        foreach ($hotels as $hotel) {
            try {
                foreach ($this->hotelPricingResolver->resolve($hotel, $request->departureDate, $request->checkOutDate(), $request->adults, $request->children, $request->childAges) as $candidate) {
                    $priced[] = ['hotel' => $hotel, 'candidate' => $candidate];
                    break;
                }
            } catch (\Throwable $exception) {
                $diagnostics['hotelResolverErrors'][] = $hotel->getName() . ': ' . $exception->getMessage();
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
    private function activityCandidates(TripSearchRequest $request, array &$messages, array &$diagnostics): array
    {
        $categories = $request->activityCategories === [] ? [null] : array_values(array_filter(array_map(
            static fn (string $value): ?ActivityCategory => ActivityCategory::tryFrom($value),
            $request->activityCategories,
        )));

        $candidates = [];
        foreach ($categories as $category) {
            try {
                foreach ($this->activityOfferResolver->resolve($request->destinationCity, $request->departureDate, $request->adults, $request->children, $request->infants, $category) as $candidate) {
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
     * @param array<int, array{hotel: Hotel, candidate: HotelPricingCandidate}> $hotels
     * @param ActivityPricingCandidate[] $activities
     * @param string[] $messages
     * @param string[] $warnings
     * @param array<string, mixed> $diagnostics
     *
     * @return TripOption[]
     */
    private function customOptions(TripSearchRequest $request, array $flights, array $hotels, array $activities, array &$messages, array &$warnings, array &$diagnostics): array
    {
        if ($flights === [] || $hotels === []) {
            return [];
        }

        $options = [];
        foreach ($flights as $flight) {
            foreach ($hotels as $hotelRow) {
                $hotel = $hotelRow['hotel'];
                $hotelCandidate = $hotelRow['candidate'];
                $transfer = $this->transferCandidate($request, $hotel, $diagnostics);
                $optionWarnings = [];
                $components = [
                    new TripComponentSummary('flight', $flight->routeSummary, $flight->sourceType->value, null, $flight->currency, $flight->totalPrice, $flight->airlineSummary . ' / ' . $flight->departureSummary),
                    new TripComponentSummary('hotel', $hotel->getName(), $hotelCandidate->sourceType->value, null, $hotelCandidate->currency, $hotelCandidate->totalPrice, $hotelCandidate->roomName),
                ];

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
                    $request->departureDate,
                    $request->returnDate,
                    $request->nightsOrDerived(),
                    $complete ? 'complete' : 'partial',
                    $budgetStatus,
                    $this->score($complete, false, $budgetStatus, $request->transferRequired && $transfer instanceof TransferPricingCandidate, $activities !== [], \count($activities), $flight->sourceType === FlightPriceSourceType::OWN || $hotelCandidate->sourceType === HotelPriceSourceType::OWN),
                    $reasons,
                    array_values(array_unique($optionWarnings)),
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
    private function transferCandidate(TripSearchRequest $request, Hotel $hotel, array &$diagnostics): ?TransferPricingCandidate
    {
        if (!$request->transferRequired) {
            return null;
        }

        try {
            $candidates = $this->transferOfferResolver->resolve(
                TransferEndpointContext::forCity($request->destinationCity),
                TransferEndpointContext::forHotel($hotel),
                $request->departureDate,
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
}
