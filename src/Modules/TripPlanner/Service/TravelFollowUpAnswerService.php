<?php

namespace App\Modules\TripPlanner\Service;

use App\Modules\TripPlanner\ValueObject\ConversationState;
use App\Modules\TripPlanner\ValueObject\HotelRecommendationSourceContext;
use App\Modules\TripPlanner\ValueObject\TripOption;

/**
 * Deterministic Persian answers to follow-up questions about the trip
 * options already shown in the current conversation.
 *
 * Never triggers a new search and never states anything not derivable from
 * the TripOption fields already displayed — every sentence is built from
 * price, board, hotel name and hotelRecommendationContext review data that
 * is already on screen. When data is missing, it says so honestly instead
 * of guessing.
 */
final class TravelFollowUpAnswerService
{
    private const INTENT_PATTERNS = [
        'destination_insight' => '/خرید|مرکز\s*خرید|مراکز\s*خرید/u',
        'breakfast' => '/صبحانه/u',
        'cheapest' => '/(کدوم|کدام)[^.\n]{0,15}(ارزون|ارزان)|(ارزون|ارزان)[^.\n]{0,15}(کدوم|کدام)/u',
        'family' => '/خانواده/u',
        'date_fit' => '/تاریخ[^.\n]{0,15}نزدیک|نزدیک[^.\n]{0,15}تاریخ/u',
        'value' => '/ارزش/u',
        'best_reviewed' => '/امتیاز|نظرات?ش|هتلش\s*چطور/u',
        'which_better' => '/(کدوم|کدام)[^.\n]{0,15}بهتر|گزینه[^.\n]{0,10}بهتر/u',
    ];

    /**
     * @return string|null the follow-up intent key, or null when the message
     *                      is not a question-style follow-up about already
     *                      shown results (either no results exist yet, or
     *                      the message reads as a new instruction/search).
     */
    public function detectIntent(string $message, bool $hasResults): ?string
    {
        if (!$hasResults) {
            return null;
        }

        $text = trim($message);
        if ($text === '') {
            return null;
        }

        $looksLikeQuestion = str_contains($text, '؟')
            || str_contains($text, 'کدوم')
            || str_contains($text, 'کدام')
            || str_contains($text, 'چطور');
        if (!$looksLikeQuestion) {
            return null;
        }

        foreach (self::INTENT_PATTERNS as $intent => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $intent;
            }
        }

        return null;
    }

    /**
     * @param TripOption[] $options the options currently shown to the user
     */
    public function answer(string $intent, array $options, ConversationState $state): string
    {
        if ($intent === 'destination_insight') {
            return $this->destinationInsightAnswer($state);
        }

        if ($options === []) {
            return 'در حال حاضر گزینه‌ای برای مقایسه در دسترس نیست.';
        }

        return match ($intent) {
            'breakfast' => $this->breakfastAnswer($options),
            'cheapest' => $this->cheapestAnswer($options),
            'family' => $this->familyAnswer($options),
            'date_fit' => $this->dateFitAnswer($options, $state),
            'value' => $this->valueAnswer($options),
            'best_reviewed' => $this->bestReviewedAnswer($options),
            'which_better' => $this->whichBetterAnswer($options),
            default => 'می‌تونی دقیق‌تر بپرسی تا بهتر راهنمایی کنم؟',
        };
    }

    /**
     * Answers a destination-advice follow-up (e.g. "کجا برای خرید برم؟")
     * strictly from the real, provider-sourced insights already fetched for
     * this conversation. Never claims a hotel is better located for the
     * purpose unless that is actually derivable from the data on hand — it
     * currently is not, so this deliberately does not compare hotels here.
     */
    private function destinationInsightAnswer(ConversationState $state): string
    {
        $insights = $state->lastDestinationInsights;
        if ($insights === []) {
            return 'در حال حاضر پیشنهاد مشخصی از منابع معتبر برای این مورد در دسترس نیست.';
        }

        $lines = [];
        foreach (\array_slice($insights, 0, 4) as $insight) {
            $line = $insight->title;
            if ($insight->rating !== null) {
                $line .= $insight->reviewCount !== null
                    ? \sprintf(' (امتیاز %s از %d نظر)', $insight->rating, $insight->reviewCount)
                    : \sprintf(' (امتیاز %s)', $insight->rating);
            }
            $lines[] = $line;
        }

        return \sprintf('بر اساس اطلاعات %s: %s.', $insights[0]->source, implode('، ', $lines));
    }

    /**
     * @param TripOption[] $options
     */
    private function breakfastAnswer(array $options): string
    {
        $withBreakfast = array_values(array_filter(
            $options,
            static fn (TripOption $option): bool => $option->board !== null && stripos($option->board, 'breakfast') !== false,
        ));

        if ($withBreakfast === []) {
            return 'در حال حاضر هیچ‌کدام از گزینه‌های نمایش داده‌شده صبحانه ندارند.';
        }

        if (\count($withBreakfast) === \count($options)) {
            return 'بله، همه گزینه‌های فعلی شامل صبحانه هستند.';
        }

        return sprintf('بله، %s صبحانه دارند.', implode('، ', array_map($this->label(...), $withBreakfast)));
    }

    /**
     * @param TripOption[] $options
     */
    private function cheapestAnswer(array $options): string
    {
        $group = $this->comparablePriceGroup($options);
        if ($group === null) {
            return \count($this->pricedCurrencies($options)) > 1
                ? 'قیمت گزینه‌های فعلی با واحدهای پولی متفاوت ثبت شده و مقایسه مستقیم آن‌ها ممکن نیست.'
                : 'در حال حاضر قیمت قابل مقایسه‌ای برای گزینه‌های فعلی ثبت نشده است.';
        }

        $cheapest = $group[0];
        $sentence = sprintf('در بین گزینه‌های فعلی، %s با قیمت %s %s ارزان‌ترین گزینه است.', $this->label($cheapest), $cheapest->totalPrice, $cheapest->currency);

        if (isset($group[1])) {
            $diff = (float) $group[1]->totalPrice - (float) $cheapest->totalPrice;
            if ($diff > 0) {
                $sentence .= sprintf(' گزینه بعدی، %s، حدود %s %s گران‌تر است.', $this->label($group[1]), number_format($diff, 2, '.', ''), $cheapest->currency);
            }
        }

        return $sentence;
    }

    /**
     * @param TripOption[] $options
     */
    private function bestReviewedAnswer(array $options): string
    {
        $rated = $this->reviewRanked($options);
        if ($rated === []) {
            return 'در حال حاضر امتیاز کاربران برای هتل‌های این گزینه‌ها ثبت نشده است.';
        }

        [$bestOption, $bestScore] = $rated[0];
        $review = $this->primaryReview($bestOption);
        $sentence = sprintf('از نظر امتیاز هتل، %s با امتیاز %s بهترین گزینه در بین موارد فعلی است.', $this->label($bestOption), $this->scoreLabel($review));
        unset($bestScore);

        if (\count($rated) > 1) {
            $sentence .= ' سایر گزینه‌ها امتیاز پایین‌تری از منابع بررسی‌شده گرفته‌اند.';
        }

        return $sentence;
    }

    /**
     * @param TripOption[] $options
     */
    private function whichBetterAnswer(array $options): string
    {
        $top = $options[0];
        foreach ($options as $option) {
            if ($option->rank === 1) {
                $top = $option;
                break;
            }
        }

        $cheapGroup = $this->comparablePriceGroup($options);
        $cheapest = $cheapGroup[0] ?? null;
        $reviewed = $this->reviewRanked($options)[0][0] ?? null;

        if ($cheapest instanceof TripOption && $reviewed instanceof TripOption && $cheapest !== $reviewed) {
            return sprintf(
                'بین گزینه‌های فعلی، %s از نظر قیمت مناسب‌تر است؛ اما اگر امتیاز هتل برایتان مهم‌تر باشد، %s انتخاب بهتری به نظر می‌رسد.',
                $this->label($cheapest),
                $this->label($reviewed),
            );
        }

        if ($cheapest instanceof TripOption) {
            return sprintf('در حال حاضر %s از نظر قیمت و شرایط، مناسب‌ترین گزینه به نظر می‌رسد.', $this->label($cheapest));
        }

        return sprintf('گزینه %s در حال حاضر گزینه پیشنهادی ماست.', $this->label($top));
    }

    /**
     * @param TripOption[] $options
     */
    private function familyAnswer(array $options): string
    {
        $scored = [];
        foreach ($options as $option) {
            $hasBreakfast = $option->board !== null && stripos($option->board, 'breakfast') !== false;
            $score = $this->normalizedReviewScore($option);
            if (!$hasBreakfast && $score === null) {
                continue;
            }
            $scored[] = ['option' => $option, 'breakfast' => $hasBreakfast, 'score' => $score ?? 0.0];
        }

        if ($scored === []) {
            return 'اطلاعات کافی درباره صبحانه یا امتیاز هتل برای مقایسه مناسب بودن این گزینه‌ها برای خانواده در دسترس نیست.';
        }

        usort($scored, static fn (array $a, array $b): int => ($b['breakfast'] <=> $a['breakfast']) ?: ($b['score'] <=> $a['score']));

        $best = $scored[0];
        $reasonParts = [];
        if ($best['breakfast']) {
            $reasonParts[] = 'صبحانه دارد';
        }
        if ($best['score'] > 0) {
            $reasonParts[] = 'امتیاز قابل قبولی از کاربران گرفته';
        }

        return sprintf('برای سفر خانوادگی، گزینه %s که %s، انتخاب مطمئن‌تری به نظر می‌رسد.', $this->label($best['option']), implode(' و ', $reasonParts));
    }

    /**
     * @param TripOption[] $options
     */
    private function dateFitAnswer(array $options, ConversationState $state): string
    {
        $target = null;
        if ($state->dateMode === 'exact' && $state->departureDate !== null) {
            $target = new \DateTimeImmutable($state->departureDate);
        } elseif ($state->windowStart !== null && $state->windowEnd !== null) {
            $start = new \DateTimeImmutable($state->windowStart);
            $end = new \DateTimeImmutable($state->windowEnd);
            $midDays = (int) ($start->diff($end)->days / 2);
            $target = $start->modify('+' . $midDays . ' days');
        }

        if ($target === null) {
            return 'بازه تاریخ مشخصی برای مقایسه ثبت نشده است.';
        }

        $distances = array_map(
            static fn (TripOption $option): array => ['option' => $option, 'diff' => abs($option->departureDate->getTimestamp() - $target->getTimestamp())],
            $options,
        );
        usort($distances, static fn (array $a, array $b): int => $a['diff'] <=> $b['diff']);

        $closest = $distances[0]['option'];
        $days = (int) round($distances[0]['diff'] / 86400);

        return $days === 0
            ? sprintf('%s دقیقاً در تاریخی که خواستید قرار دارد.', $this->label($closest))
            : sprintf('%s نزدیک‌ترین گزینه به بازه تاریخی موردنظر شماست (حدود %d روز فاصله).', $this->label($closest), $days);
    }

    /**
     * @param TripOption[] $options
     */
    private function valueAnswer(array $options): string
    {
        $cheapest = $this->comparablePriceGroup($options)[0] ?? null;
        $reviewed = $this->reviewRanked($options)[0][0] ?? null;

        if ($cheapest instanceof TripOption && $reviewed instanceof TripOption && $cheapest === $reviewed) {
            return sprintf('%s هم قیمت مناسبی دارد و هم امتیاز خوبی از کاربران گرفته؛ از نظر ارزش خرید گزینه مطمئنی است.', $this->label($cheapest));
        }

        if ($cheapest instanceof TripOption && $reviewed instanceof TripOption) {
            return sprintf('از نظر قیمت %s و از نظر امتیاز هتل %s در جایگاه بهتری هستند؛ بسته به اولویت شما، هرکدام می‌تواند انتخاب مناسبی باشد.', $this->label($cheapest), $this->label($reviewed));
        }

        if ($cheapest instanceof TripOption) {
            return sprintf('در حال حاضر %s از نظر قیمت مناسب‌ترین گزینه است.', $this->label($cheapest));
        }

        return 'اطلاعات کافی برای مقایسه ارزش خرید گزینه‌های فعلی در دسترس نیست.';
    }

    /**
     * Options priced in the majority/only currency present, sorted cheapest
     * first. Returns null when nothing is priced or prices mix currencies
     * (never fake-converts, matching the rest of the planner).
     *
     * @param TripOption[] $options
     *
     * @return TripOption[]|null
     */
    private function comparablePriceGroup(array $options): ?array
    {
        $byCurrency = [];
        foreach ($options as $option) {
            if ($option->totalPrice !== null && $option->currency !== null) {
                $byCurrency[$option->currency][] = $option;
            }
        }

        if (\count($byCurrency) !== 1) {
            return null;
        }

        $group = reset($byCurrency);
        usort($group, static fn (TripOption $a, TripOption $b): int => (float) $a->totalPrice <=> (float) $b->totalPrice);

        return $group;
    }

    /**
     * @param TripOption[] $options
     *
     * @return string[]
     */
    private function pricedCurrencies(array $options): array
    {
        $currencies = [];
        foreach ($options as $option) {
            if ($option->totalPrice !== null && $option->currency !== null) {
                $currencies[$option->currency] = true;
            }
        }

        return array_keys($currencies);
    }

    /**
     * @param TripOption[] $options
     *
     * @return array<int, array{0: TripOption, 1: float}> sorted best first
     */
    private function reviewRanked(array $options): array
    {
        $rated = [];
        foreach ($options as $option) {
            $score = $this->normalizedReviewScore($option);
            if ($score !== null) {
                $rated[] = [$option, $score];
            }
        }

        usort($rated, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

        return $rated;
    }

    private function normalizedReviewScore(TripOption $option): ?float
    {
        $review = $this->primaryReview($option);
        if ($review === null || $review->reviewScore === null) {
            return null;
        }

        $scale = $review->reviewScoreScale !== null ? (float) $review->reviewScoreScale : null;

        return $scale !== null && $scale > 0 ? (float) $review->reviewScore / $scale : (float) $review->reviewScore;
    }

    private function primaryReview(TripOption $option): ?HotelRecommendationSourceContext
    {
        $context = $option->hotelRecommendationContext;
        if ($context === null) {
            return null;
        }

        foreach ($context->sources as $source) {
            if ($source->reviewScore !== null) {
                return $source;
            }
        }

        return null;
    }

    private function scoreLabel(?HotelRecommendationSourceContext $review): string
    {
        if ($review === null || $review->reviewScore === null) {
            return '';
        }

        return $review->reviewScoreScale !== null
            ? sprintf('%s از %s', $review->reviewScore, $review->reviewScoreScale)
            : $review->reviewScore;
    }

    private function label(TripOption $option): string
    {
        return $option->hotelName ?? $option->title;
    }
}
