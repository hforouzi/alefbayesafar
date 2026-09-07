<?php

namespace App\Modules\TripPlanner\Service;

use App\Modules\TripPlanner\ValueObject\ConversationState;
use App\Modules\TripPlanner\ValueObject\TravelIntentUpdate;

/**
 * Keyword/pattern based Persian intent interpreter.
 *
 * Works entirely offline (no DB, no network) so it is always available as a
 * fallback when OpenAI is not configured or fails. It never resolves place
 * names against the catalog itself — it only extracts candidate phrases;
 * ConversationalTravelPlanningService resolves those against
 * CityRepository/CountryRepository.
 */
final class DeterministicTravelIntentInterpreter implements TravelIntentInterpreterInterface
{
    private const NUMBER_WORDS = [
        'یک' => 1, 'دو' => 2, 'سه' => 3, 'چهار' => 4, 'پنج' => 5,
        'شش' => 6, 'هفت' => 7, 'هشت' => 8, 'نه' => 9, 'ده' => 10,
    ];

    private const MONTHS = [
        'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
    ];

    /**
     * Broad, relative or seasonal period phrases. Longest phrases first so a
     * two-word phrase is matched before any single-word overlap could occur.
     */
    private const PERIOD_HINTS = [
        'این ماه', 'ماه بعد', 'امروز', 'فردا', 'پاییز', 'زمستان', 'بهار', 'تابستان',
    ];

    private const STOPWORDS = [
        'می‌خوام', 'میخوام', 'می‌خواهم', 'برم', 'برای', 'هستم', 'باشه', 'باشد', 'دارم', 'دارد',
        'است', 'و', 'یا', 'هر', 'وقت', 'زمان', 'تو', 'من', 'یه', 'یک', 'به', 'از', 'که', 'رو',
        'می‌مونم', 'میمونم', 'سفر', 'رفتن', 'میرم', 'می‌رم', 'کنم', 'نشون', 'بده', 'بدید',
    ];

    public function interpret(string $message, ConversationState $state): TravelIntentUpdate
    {
        $text = $this->normalizeDigits(trim($message));
        if ($text === '') {
            return new TravelIntentUpdate();
        }

        $working = ' ' . $text . ' ';
        $recognized = false;

        $hotelStarPreference = null;
        if (preg_match('/(\d+)\s*ستاره/u', $working, $m) === 1) {
            $hotelStarPreference = min(5, max(1, (int) $m[1]));
            $working = $this->strip($working, $m[0]);
            $recognized = true;
        }

        $budget = null;
        if (preg_match('/بودجه[^\d]{0,10}(\d+)\s*(میلیون|هزار)?/u', $working, $m) === 1
            || preg_match('/(\d+)\s*(میلیون|هزار)\s*تومان?/u', $working, $m) === 1) {
            $multiplier = match ($m[2] ?? '') {
                'میلیون' => 1_000_000,
                'هزار' => 1_000,
                default => 1,
            };
            $budget = (string) ((int) $m[1] * $multiplier);
            $working = $this->strip($working, $m[0]);
            $recognized = true;
        }

        $nights = $this->consumeNumberBefore($working, ['شب', 'روز'], $recognized);

        $adults = $this->consumeNumberBefore($working, ['نفر'], $recognized);

        $children = null;
        if (preg_match('/(?:(\d+|' . $this->wordAlternation() . ')\s*(?:تا)?\s*)?(?:بچه|کودک)/u', $working, $m) === 1) {
            $children = isset($m[1]) && $m[1] !== '' ? $this->numberValue($m[1]) : 1;
            $working = $this->strip($working, $m[0]);
            $recognized = true;
        }

        $monthHint = null;
        foreach ([...self::PERIOD_HINTS, ...self::MONTHS] as $period) {
            if (preg_match('/(?<![\p{L}])' . preg_quote($period, '/') . '(?![\p{L}])/u', $working) === 1) {
                $monthHint = $period;
                $working = preg_replace('/(?<![\p{L}])' . preg_quote($period, '/') . '(?![\p{L}])/u', ' ', $working) ?? $working;
                $recognized = true;
                break;
            }
        }

        $breakfastPreferred = null;
        if (mb_strpos($working, 'صبحانه') !== false) {
            $breakfastPreferred = true;
            $working = str_replace('صبحانه', ' ', $working);
            $recognized = true;
        }

        $directFlightPreferred = null;
        if (mb_strpos($working, 'مستقیم') !== false) {
            $directFlightPreferred = true;
            $working = str_replace('مستقیم', ' ', $working);
            $recognized = true;
        }

        $widenWindowRequested = false;
        if (preg_match('/بازتر|باز\s*کن/u', $working, $m) === 1) {
            $widenWindowRequested = true;
            $working = $this->strip($working, $m[0]);
            $recognized = true;
        }

        $anotherCityRequested = false;
        if (preg_match('/شهر\s*(دیگه|دیگر)/u', $working, $m) === 1) {
            $anotherCityRequested = true;
            $working = $this->strip($working, $m[0]);
            $recognized = true;
        }

        $cheaperRequested = false;
        if (preg_match('/ارزون‌?تر|ارزان‌?تر/u', $working, $m) === 1) {
            $cheaperRequested = true;
            $working = $this->strip($working, $m[0]);
            $recognized = true;
        }

        $betterHotelRequested = false;
        if (preg_match('/هتل[^\.\n]{0,6}بهتر|بهتر[^\.\n]{0,6}هتل/u', $working, $m) === 1) {
            $betterHotelRequested = true;
            $working = $this->strip($working, $m[0]);
            $recognized = true;
        } elseif (preg_match('/هتل[^\.\n]{0,6}خوب|خوب[^\.\n]{0,6}هتل/u', $working, $m) === 1) {
            $hotelStarPreference ??= 4;
            $working = $this->strip($working, $m[0]);
            $recognized = true;
        }

        $destinationOpen = false;
        if (preg_match('/هر\s*جا/u', $working, $m) === 1) {
            $destinationOpen = true;
            $working = $this->strip($working, $m[0]);
            $recognized = true;
        } elseif (mb_strpos($working, 'ارزون') !== false || mb_strpos($working, 'ارزان') !== false) {
            // A bare "cheap trip" wish without "هر جا" only opens the
            // destination when nothing else in this message named a place.
            $destinationOpen = true;
            $recognized = true;
        }
        $working = str_replace(['ارزون', 'ارزان'], ' ', $working);

        $flexibleHint = false;
        if (preg_match('/هر\s*وقت|منعطف|هر\s*زمان|تاریخ\s*مهم\s*نیست|هر\s*تاریخ|فرقی\s*نداره|فرقی\s*نمی‌?کنه|مهم\s*نیست/u', $working, $m) === 1) {
            $flexibleHint = true;
            $working = $this->strip($working, $m[0]);
            $recognized = true;
        }

        $originCandidate = null;
        if (preg_match('/از\s+([\p{L}‌]+)/u', $working, $m) === 1) {
            $originCandidate = $this->cleanWord($m[1]);
            $working = $this->strip($working, $m[0]);
        }

        $destinationAnchorCandidate = null;
        if (preg_match('/به\s+([\p{L}‌]+)/u', $working, $m) === 1) {
            $destinationAnchorCandidate = $this->cleanWord($m[1]);
            $working = $this->strip($working, $m[0]);
        }

        $leftoverTokens = $this->leftoverPlaceTokens($working);

        $destinationCandidate = $destinationAnchorCandidate;
        // A bare leftover word claims the origin slot either while origin is
        // still being gathered, or — once origin and destination are both
        // already settled — as an explicit "actually, I'm leaving from X"
        // correction. (While only origin is known and destination is still
        // being asked about, a bare word goes to the destination branch
        // below instead, so it is never mistaken for an origin change.)
        $allowOriginLeftover = $state->awaitingField === 'origin'
            || ($state->hasOrigin() && $state->hasDestinationScope());
        if ($originCandidate === null && $allowOriginLeftover && $leftoverTokens !== []) {
            $originCandidate = array_shift($leftoverTokens);
        }
        // A bare, un-anchored leftover word is only treated as a destination
        // guess while no destination is known yet. Once a destination is
        // already resolved, a stray word in a later message (e.g. a season
        // or "whenever is cheaper" reply) must never silently overwrite it.
        if ($destinationCandidate === null && !$state->hasDestinationScope() && $leftoverTokens !== []) {
            $destinationCandidate = array_shift($leftoverTokens);
        }

        if ($originCandidate !== null || $destinationCandidate !== null) {
            $recognized = true;
        }
        if ($destinationOpen) {
            // "cheap trip anywhere" should not also carry a stray place guess.
            $destinationCandidate ??= null;
        }

        return new TravelIntentUpdate(
            originCityName: $originCandidate,
            destinationCityName: $destinationCandidate,
            destinationOpen: $destinationOpen,
            nights: $nights,
            adults: $adults,
            children: $children,
            monthHint: $monthHint,
            flexibleHint: $flexibleHint,
            budget: $budget,
            hotelStarPreference: $hotelStarPreference,
            breakfastPreferred: $breakfastPreferred,
            directFlightPreferred: $directFlightPreferred,
            cheaperRequested: $cheaperRequested,
            betterHotelRequested: $betterHotelRequested,
            widenWindowRequested: $widenWindowRequested,
            anotherCityRequested: $anotherCityRequested,
            recognized: $recognized,
        );
    }

    /**
     * @param string[] $keywords
     */
    private function consumeNumberBefore(string &$working, array $keywords, bool &$recognized): ?int
    {
        $alternation = implode('|', array_map(static fn (string $k): string => preg_quote($k, '/'), $keywords));
        if (preg_match('/(\d+|' . $this->wordAlternation() . ')\s*(?:تا)?\s*(?:' . $alternation . ')/u', $working, $m) === 1) {
            $working = $this->strip($working, $m[0]);
            $recognized = true;

            return $this->numberValue($m[1]);
        }

        return null;
    }

    private function numberValue(string $token): int
    {
        if (ctype_digit($token)) {
            return (int) $token;
        }

        return self::NUMBER_WORDS[$token] ?? 1;
    }

    private function wordAlternation(): string
    {
        return implode('|', array_keys(self::NUMBER_WORDS));
    }

    private function normalizeDigits(string $text): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $ascii = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($arabic, $ascii, str_replace($persian, $ascii, $text));
    }

    private function strip(string $working, string $match): string
    {
        return str_replace($match, ' ', $working);
    }

    private function cleanWord(string $word): string
    {
        return preg_replace('/^[\s‌،.,!؟?]+|[\s‌،.,!؟?]+$/u', '', $word) ?? $word;
    }

    /**
     * @return string[]
     */
    private function leftoverPlaceTokens(string $working): array
    {
        $working = preg_replace('/[،.,!؟?]/u', ' ', $working) ?? $working;
        $tokens = preg_split('/\s+/u', trim($working)) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));

        $tokens = array_values(array_filter(
            $tokens,
            fn (string $token): bool => !\in_array($token, self::STOPWORDS, true) && mb_strlen($token) >= 2,
        ));

        return array_map($this->cleanWord(...), $tokens);
    }
}
