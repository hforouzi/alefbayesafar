<?php

namespace App\Modules\TripPlanner\Service;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Repository\AirportRepository;
use App\Modules\Destination\Repository\CityRepository;
use App\Modules\Destination\Repository\CountryRepository;
use App\Modules\TripPlanner\Enum\TripDateMode;
use App\Modules\TripPlanner\Enum\TripPlanningGoal;
use App\Modules\TripPlanner\Enum\TripPlanStatus;
use App\Modules\TripPlanner\ValueObject\ConversationState;
use App\Modules\TripPlanner\ValueObject\TravelIntentUpdate;
use App\Modules\TripPlanner\ValueObject\TripSearchRequest;

/**
 * Orchestrates one chat turn for the public conversational trip planner.
 *
 * Owns no search/provider logic itself: it only (1) interprets the message,
 * (2) resolves place-name guesses against the catalog, (3) decides the next
 * clarification question or builds a TripSearchRequest, and (4) delegates
 * to the existing TravelPlanningService + explanation service.
 */
final readonly class ConversationalTravelPlanningService
{
    private const MONTH_RANGES = [
        'فروردین' => [3, 21, 4, 20],
        'اردیبهشت' => [4, 21, 5, 21],
        'خرداد' => [5, 22, 6, 21],
        'تیر' => [6, 22, 7, 22],
        'مرداد' => [7, 23, 8, 22],
        'شهریور' => [8, 23, 9, 22],
        'مهر' => [9, 23, 10, 22],
        'آبان' => [10, 23, 11, 21],
        'آذر' => [11, 22, 12, 21],
        'دی' => [12, 22, 1, 20],
        'بهمن' => [1, 21, 2, 19],
        'اسفند' => [2, 20, 3, 20],
        // Seasons: same [startMonth, startDay, endMonth, endDay] shape, just wider.
        'پاییز' => [9, 23, 12, 21],
        'زمستان' => [12, 22, 3, 20],
        'بهار' => [3, 21, 6, 21],
        'تابستان' => [6, 22, 9, 22],
    ];

    /** Repeats of the same clarification field before switching to an alternate question with quick-reply chips. */
    private const MAX_SAME_QUESTION_REPEATS = 0;

    private const PURPOSE_LABELS = [
        'shopping' => 'خرید',
        'food' => 'غذا و رستوران‌گردی',
        'history' => 'بازدید تاریخی',
        'nightlife' => 'تفریح شبانه',
        'family' => 'گردش خانوادگی',
    ];

    public function __construct(
        private TravelIntentInterpreterInterface $interpreter,
        private CityRepository $cityRepository,
        private CountryRepository $countryRepository,
        private AirportRepository $airportRepository,
        private TravelPlanningService $planningService,
        private TravelRecommendationExplanationServiceInterface $explanationService,
        private TravelFollowUpAnswerService $followUpAnswerService,
        private DestinationInsightService $destinationInsightService,
    ) {
    }

    public function handleMessage(ConversationState $state, string $message): ConversationState
    {
        $message = trim($message);
        if ($message === '') {
            return $state;
        }

        $state->addMessage('user', $message);

        $hasResults = !empty($state->lastResultOptions) || $state->lastDestinationInsights !== [];
        $followUpIntent = $this->followUpAnswerService->detectIntent($message, $hasResults);
        if ($followUpIntent !== null) {
            $state->addMessage('assistant', $this->followUpAnswerService->answer($followUpIntent, $state->lastResultOptions ?? [], $state));

            return $state;
        }

        $previousAwaitingField = $state->awaitingField;
        $update = $this->interpreter->interpret($message, $state);
        $this->applyUpdate($state, $update);

        $nextField = $this->awaitingFieldFor($state);
        if ($nextField !== null) {
            $state->awaitingFieldRepeatCount = $nextField === $previousAwaitingField
                ? $state->awaitingFieldRepeatCount + 1
                : 0;
            $state->awaitingField = $nextField;

            $clarification = $this->clarificationFor($nextField, $state->awaitingFieldRepeatCount);
            $state->suggestedReplies = $clarification['chips'];
            $state->addMessage('assistant', $clarification['question']);

            return $state;
        }

        $state->awaitingField = null;
        $state->awaitingFieldRepeatCount = 0;
        $state->suggestedReplies = [];
        $this->runSearch($state, $update);

        return $state;
    }

    private function applyUpdate(ConversationState $state, TravelIntentUpdate $update): void
    {
        if ($update->originCityName !== null) {
            $city = $this->findCity($update->originCityName);
            if ($city instanceof City) {
                $state->originCityId = $city->getId();
                $state->originCityName = $city->getNameFa() ?? $city->getName();
            }
        }

        if ($update->destinationCityName !== null) {
            $city = $this->findCity($update->destinationCityName);
            if ($city instanceof City) {
                $state->destinationCityId = $city->getId();
                $state->destinationCityName = $city->getNameFa() ?? $city->getName();
                $state->destinationCountryId = $city->getCountry()?->getId();
                $state->destinationCountryName = $city->getCountry()?->getNameFa() ?? $city->getCountry()?->getName();
                $state->destinationOpen = false;
            } else {
                $country = $this->findCountry($update->destinationCityName);
                if ($country instanceof Country) {
                    $state->destinationCountryId = $country->getId();
                    $state->destinationCountryName = $country->getNameFa() ?? $country->getName();
                    $state->destinationOpen = false;
                }
            }
        }

        if ($update->destinationOpen) {
            // "Cheapest trip" is a lasting preference: keep it even once a
            // concrete destination is resolved later in the conversation.
            $state->goal = 'cheapest';
            if (!$state->hasDestinationScope()) {
                $state->destinationOpen = true;
            }
        }

        if ($update->nights !== null) {
            $state->nights = $update->nights;
        }
        if ($update->adults !== null) {
            $state->adults = $update->adults;
        }
        if ($update->children !== null) {
            $state->children = $update->children;
        }
        if ($update->childrenAges !== null) {
            $state->childrenAges = $update->childrenAges;
        }
        if ($update->budget !== null) {
            $state->budget = $update->budget;
        }
        if ($update->hotelStarPreference !== null) {
            $state->hotelStarPreference = $update->hotelStarPreference;
        }
        if ($update->breakfastPreferred !== null) {
            $state->breakfastPreferred = $update->breakfastPreferred;
        }
        if ($update->directFlightPreferred !== null) {
            $state->directFlightPreferred = $update->directFlightPreferred;
        }

        if ($update->flexibleHint) {
            $state->dateMode = 'flexible';
        }

        if ($update->monthHint !== null) {
            $window = $this->windowForPeriod($update->monthHint, new \DateTimeImmutable());
            if ($window !== null) {
                $state->dateMode = 'flexible';
                $state->windowStart = $window['start']->format('Y-m-d');
                $state->windowEnd = $window['end']->format('Y-m-d');
                $state->nights ??= 5;
            }
        }

        // "Whenever is cheaper" / "no preference" is a definitive statement
        // that no exact period is coming — if nothing concrete was ever
        // captured, apply one sensible broad window instead of leaving the
        // conversation stuck asking for a date it will never get.
        if ($update->flexibleHint && !$state->hasAnyDateSignal()) {
            $fallbackStart = new \DateTimeImmutable('+7 days');
            $state->windowStart = $fallbackStart->format('Y-m-d');
            $state->windowEnd = $fallbackStart->modify('+120 days')->format('Y-m-d');
            $state->nights ??= 5;
        }

        if ($update->betterHotelRequested) {
            $state->hotelStarPreference = min(5, ($state->hotelStarPreference ?? 3) + 1);
        }

        if ($update->widenWindowRequested) {
            $this->widenWindow($state);
        }

        if ($update->anotherCityRequested && $state->destinationCountryId !== null) {
            $state->destinationCityId = null;
            $state->destinationCityName = null;
        }

        if ($update->purpose !== null) {
            $state->travelPurpose = $update->purpose;
        }
    }

    private function widenWindow(ConversationState $state): void
    {
        if ($state->windowStart !== null && $state->windowEnd !== null) {
            $start = new \DateTimeImmutable($state->windowStart);
            $end = new \DateTimeImmutable($state->windowEnd);
            $state->windowStart = $start->modify('-10 days')->format('Y-m-d');
            $state->windowEnd = $end->modify('+10 days')->format('Y-m-d');

            return;
        }

        if ($state->departureDate !== null && $state->returnDate !== null) {
            $departure = new \DateTimeImmutable($state->departureDate);
            $return = new \DateTimeImmutable($state->returnDate);
            $state->dateMode = 'flexible';
            $state->windowStart = $departure->modify('-10 days')->format('Y-m-d');
            $state->windowEnd = $return->modify('+10 days')->format('Y-m-d');
            $state->nights ??= (int) $departure->diff($return)->format('%a');
        }
    }

    private function findCity(string $name): ?City
    {
        $matches = $this->cityRepository->search($name, null, null, 3);
        foreach ($matches as $match) {
            if ($match->getName() === $name || $match->getNameFa() === $name) {
                return $match;
            }
        }

        return $matches[0] ?? null;
    }

    private function findCountry(string $name): ?Country
    {
        $matches = $this->countryRepository->search($name, 3);
        foreach ($matches as $match) {
            if ($match->getName() === $name || $match->getNameFa() === $name) {
                return $match;
            }
        }

        return $matches[0] ?? null;
    }

    /**
     * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable}|null
     */
    private function windowForPeriod(string $period, \DateTimeImmutable $now): ?array
    {
        if ($period === 'امروز') {
            return ['start' => $now, 'end' => $now->modify('+14 days')];
        }
        if ($period === 'فردا') {
            $tomorrow = $now->modify('+1 day');

            return ['start' => $tomorrow, 'end' => $tomorrow->modify('+14 days')];
        }
        if ($period === 'این ماه') {
            return ['start' => $now, 'end' => $now->modify('+30 days')];
        }
        if ($period === 'ماه بعد') {
            $start = $now->modify('+30 days');

            return ['start' => $start, 'end' => $start->modify('+30 days')];
        }

        if (!isset(self::MONTH_RANGES[$period])) {
            return null;
        }

        [$startMonth, $startDay, $endMonth, $endDay] = self::MONTH_RANGES[$period];
        $crossesYear = $endMonth < $startMonth;
        $year = (int) $now->format('Y');

        $start = $this->dateFrom($year, $startMonth, $startDay);
        if ($start < $now->modify('-1 day')) {
            ++$year;
            $start = $this->dateFrom($year, $startMonth, $startDay);
        }

        $end = $this->dateFrom($crossesYear ? $year + 1 : $year, $endMonth, $endDay);

        return ['start' => $start, 'end' => $end];
    }

    private function dateFrom(int $year, int $month, int $day): \DateTimeImmutable
    {
        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    /**
     * Single source of truth for readiness: returns the next field that
     * still blocks a search, or null once enough is known to search.
     */
    private function awaitingFieldFor(ConversationState $state): ?string
    {
        if (!$state->hasOrigin()) {
            return 'origin';
        }
        if ($state->destinationOpen && $state->destinationCountryId === null) {
            return 'destination';
        }
        if (!$state->hasDestinationScope()) {
            return 'destination';
        }
        if (!$state->hasAnyDateSignal()) {
            return 'date';
        }

        return null;
    }

    /**
     * @return array{question: string, chips: string[]}
     */
    private function clarificationFor(string $field, int $repeatCount): array
    {
        $repeating = $repeatCount > self::MAX_SAME_QUESTION_REPEATS;

        return match ($field) {
            'origin' => [
                'question' => $repeating
                    ? 'هنوز مبدأ سفر رو متوجه نشدم. اسم شهری که ازش حرکت می‌کنی رو بنویس، مثلاً «رشت» یا «تهران».'
                    : 'از کدام شهر حرکت می‌کنی؟',
                'chips' => [],
            ],
            'destination' => [
                'question' => $repeating
                    ? 'هنوز مقصد مشخص نشده. اسم یک شهر یا کشور رو بنویس، مثلاً «استانبول» یا «ترکیه».'
                    : 'به کجا می‌خوای سفر کنی؟ (شهر یا کشور)',
                'chips' => [],
            ],
            'date' => [
                'question' => $repeating
                    ? 'می‌تونی یکی از این‌ها رو انتخاب کنی:'
                    : 'تاریخ خاصی مدنظرته یا هر زمانی که ارزون‌تر باشه؟',
                'chips' => $repeating ? ['پاییز', 'ماه بعد', 'تاریخ مهم نیست'] : [],
            ],
            default => ['question' => 'می‌تونی بیشتر توضیح بدی؟', 'chips' => []],
        };
    }

    private function runSearch(ConversationState $state, TravelIntentUpdate $update): void
    {
        $originCity = $state->originCityId !== null ? $this->cityRepository->find($state->originCityId) : null;
        $destinationCity = $state->destinationCityId !== null ? $this->cityRepository->find($state->destinationCityId) : null;
        $destinationCountry = $state->destinationCountryId !== null ? $this->countryRepository->find($state->destinationCountryId) : null;

        if (!$originCity instanceof City || (!$destinationCity instanceof City && !$destinationCountry instanceof Country)) {
            $state->addMessage('assistant', 'متاسفانه یکی از اطلاعات لازم برای جست‌وجو در دسترس نیست. لطفاً دوباره امتحان کن.');

            return;
        }

        $originAirport = $this->airportRepository->search('', $originCity, 1)[0] ?? null;
        $nights = $state->nights ?? 5;

        $dateMode = $state->dateMode === 'exact' && $state->departureDate !== null && $state->returnDate !== null
            ? TripDateMode::EXACT
            : TripDateMode::FLEXIBLE;

        $windowStart = $state->windowStart !== null ? new \DateTimeImmutable($state->windowStart) : null;
        $windowEnd = $state->windowEnd !== null ? new \DateTimeImmutable($state->windowEnd) : null;
        if ($dateMode === TripDateMode::FLEXIBLE && ($windowStart === null || $windowEnd === null)) {
            $windowStart ??= new \DateTimeImmutable('+7 days');
            $windowEnd ??= $windowStart->modify('+45 days');
        }

        try {
            $request = new TripSearchRequest(
                originAirport: $originAirport,
                originCity: $originCity,
                destinationCity: $destinationCity,
                departureDate: $dateMode === TripDateMode::EXACT ? new \DateTimeImmutable((string) $state->departureDate) : null,
                returnDate: $dateMode === TripDateMode::EXACT ? new \DateTimeImmutable((string) $state->returnDate) : null,
                nights: $dateMode === TripDateMode::FLEXIBLE ? $nights : null,
                adults: $state->adults,
                children: $state->children,
                infants: 0,
                childAges: $state->childrenAges,
                budget: $state->budget,
                preferredCurrency: 'IRR',
                hotelStarPreference: $state->hotelStarPreference,
                breakfastPreferred: (bool) $state->breakfastPreferred,
                directFlightPreferred: $state->directFlightPreferred,
                dateMode: $dateMode,
                windowStart: $dateMode === TripDateMode::FLEXIBLE ? $windowStart : null,
                windowEnd: $dateMode === TripDateMode::FLEXIBLE ? $windowEnd : null,
                goal: TripPlanningGoal::SPECIFIC_DESTINATION,
                destinationCountry: $destinationCountry,
            );
        } catch (\InvalidArgumentException) {
            $state->addMessage('assistant', 'متاسفانه این ترکیب از تاریخ و اطلاعات سفر قابل جست‌وجو نیست. می‌تونی دوباره امتحان کنی؟');

            return;
        }

        $state->searchAttempted = true;
        $result = $this->planningService->plan($request);
        $explanation = $this->explanationService->explain($request, $result);

        if ($result->options === []) {
            $state->lastResultOptions = [];
            $state->lastExplanationOverall = null;
            $state->lastExplanationPerOption = [];
            $state->lastNoResultMessages = $result->messages;

            $place = $state->destinationCityName ?? $state->destinationCountryName ?? 'این مقصد';
            $state->addMessage(
                'assistant',
                \sprintf(
                    'برای %s در این بازه فعلاً گزینه مناسبی پیدا نکردم. اگر بخوای می‌تونم بازه تاریخ رو بازتر کنم یا شهرهای دیگه رو بررسی کنم.',
                    $place,
                ),
            );
            $this->applyDestinationInsights($state);

            return;
        }

        $state->lastResultOptions = $result->options;
        $state->lastExplanationOverall = $explanation->overallText;
        $state->lastExplanationPerOption = $explanation->perOptionText;
        $state->lastNoResultMessages = null;

        $summary = \sprintf('برای %s شب %s، %d گزینه مناسب پیدا کردم.', $nights, $state->destinationCityName ?? $state->destinationCountryName ?? '', \count($result->options));
        if ($update->cheaperRequested) {
            $summary .= ' گزینه‌ها بر اساس قیمت مرتب شده‌اند؛ گزینه اول در حال حاضر کمترین قیمت مقایسه‌شده است.';
        }
        $state->addMessage('assistant', $summary);
        if ($explanation->overallText !== '') {
            $state->addMessage('assistant', $explanation->overallText);
        }

        $this->applyDestinationInsights($state);
    }

    /**
     * Fetches real destination-advice items (shopping malls, markets, ...)
     * once a purpose and destination are both known, and appends one honest
     * Persian summary message. Stays silent on failure/no-data rather than
     * leaking a technical provider error into the public chat.
     */
    private function applyDestinationInsights(ConversationState $state): void
    {
        if ($state->travelPurpose === null) {
            return;
        }

        $city = $state->destinationCityName ?? $state->destinationCountryName;
        if ($city === null) {
            return;
        }

        $result = $this->destinationInsightService->forPurpose($city, $state->destinationCountryName, $state->travelPurpose);
        $state->lastDestinationInsights = $result->insights;
        $purposeLabel = self::PURPOSE_LABELS[$state->travelPurpose] ?? $state->travelPurpose;

        if ($result->insights !== []) {
            $names = implode('، ', array_map(
                static fn ($insight): string => $insight->title,
                \array_slice($result->insights, 0, 3),
            ));
            $state->addMessage('assistant', \sprintf('برای %s در %s، بر اساس منابع معتبر می‌تونی این‌ها رو در نظر بگیری: %s.', $purposeLabel, $city, $names));
        } elseif ($result->success) {
            $state->addMessage('assistant', \sprintf('در حال حاضر پیشنهاد مشخصی برای %s در %s از منابع معتبر پیدا نشد.', $purposeLabel, $city));
        }
    }
}
