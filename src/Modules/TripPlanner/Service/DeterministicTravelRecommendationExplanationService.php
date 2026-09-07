<?php

namespace App\Modules\TripPlanner\Service;

use App\Modules\TripPlanner\ValueObject\HotelRecommendationSourceContext;
use App\Modules\TripPlanner\ValueObject\TravelRecommendationExplanation;
use App\Modules\TripPlanner\ValueObject\TripOption;
use App\Modules\TripPlanner\ValueObject\TripPlanResult;
use App\Modules\TripPlanner\ValueObject\TripSearchRequest;

/**
 * Deterministic, template-based Persian explanation of a trip plan result.
 *
 * Works without any AI credentials and only restates facts already present
 * on the request/result (destination, dates, price, hotel board, review
 * score). It never invents information — every sentence is built strictly
 * from structured TripOption fields, never from raw internal
 * reason/warning strings meant for admin diagnostics.
 */
final readonly class DeterministicTravelRecommendationExplanationService implements TravelRecommendationExplanationServiceInterface
{
    public function explain(TripSearchRequest $request, TripPlanResult $result): TravelRecommendationExplanation
    {
        if ($result->options === []) {
            return new TravelRecommendationExplanation($this->noOptionsText($request));
        }

        $best = $result->options[0];
        foreach ($result->options as $option) {
            if ($option->rank === 1) {
                $best = $option;
                break;
            }
        }

        $perOption = [];
        foreach ($result->options as $option) {
            $perOption[$option->rank] = $this->explainOption($option, $option === $best);
        }

        return new TravelRecommendationExplanation($this->overallText($request, $result, $best), $perOption);
    }

    private function noOptionsText(TripSearchRequest $request): string
    {
        $destination = $this->destinationLabel($request);

        return $destination !== null
            ? sprintf('در حال حاضر برای %s در این بازه گزینه مناسبی پیدا نشد. می‌توانید بازه تاریخ را بازتر کنید یا شهرهای دیگر همان کشور را بررسی کنید.', $destination)
            : 'در حال حاضر برای این جست‌وجو گزینه مناسبی پیدا نشد. می‌توانید بازه تاریخ را بازتر کنید یا مقصد دیگری را امتحان کنید.';
    }

    private function overallText(TripSearchRequest $request, TripPlanResult $result, TripOption $best): string
    {
        $destination = $best->destinationCity ?? $best->destinationCountry ?? $this->destinationLabel($request);
        $origin = $request->originCity?->getNameFa() ?? $request->originCity?->getName();
        $count = \count($result->options);

        $tripLabel = sprintf('سفر %d شبه شما', $best->nights);
        if ($origin !== null && $destination !== null) {
            $tripLabel .= sprintf(' از %s به %s', $origin, $destination);
        } elseif ($destination !== null) {
            $tripLabel .= sprintf(' به %s', $destination);
        }

        $sentences = [];
        $sentences[] = sprintf(
            'برای %s، %d گزینه پیدا شد؛ مناسب‌ترین گزینه‌ها را بر اساس اطلاعات موجود از منابع معتبر نمایش داده‌ایم.',
            $tripLabel,
            $count,
        );

        if ($best->totalPrice !== null && $best->currency !== null) {
            $sentences[] = $best->hotelName !== null
                ? sprintf('گزینه پیشنهادی با قیمت %s %s در هتل %s است.', $best->totalPrice, $best->currency, $best->hotelName)
                : sprintf('گزینه پیشنهادی با قیمت %s %s است.', $best->totalPrice, $best->currency);
        }

        $insight = $this->comparativeInsight($result->options);
        if ($insight !== null) {
            $sentences[] = $insight;
        }

        return implode(' ', $sentences);
    }

    /**
     * A one-sentence planning takeaway comparing the priced options, so the
     * top-of-results summary reads like an assistant's advice rather than a
     * plain count. Only compares prices already in the same currency —
     * never fake-converts — and stays silent when there is nothing useful
     * to compare.
     *
     * @param TripOption[] $options
     */
    private function comparativeInsight(array $options): ?string
    {
        $byCurrency = [];
        foreach ($options as $option) {
            if ($option->totalPrice !== null && $option->currency !== null) {
                $byCurrency[$option->currency][] = (float) $option->totalPrice;
            }
        }

        if (\count($byCurrency) !== 1) {
            return null;
        }

        $prices = reset($byCurrency);
        if (\count($prices) < 2) {
            return null;
        }

        $min = min($prices);
        $max = max($prices);
        if ($min <= 0) {
            return null;
        }

        return (($max - $min) / $min) <= 0.15
            ? 'بیشتر گزینه‌های فعلی در یک بازه قیمتی مشابه هستند؛ تفاوت اصلی بیشتر در کیفیت هتل و امتیاز کاربران است.'
            : 'قیمت گزینه‌های فعلی تفاوت محسوسی دارد؛ می‌توانید بر اساس بودجه یا کیفیت هتل انتخاب کنید.';
    }

    private function explainOption(TripOption $option, bool $isBest): string
    {
        $sentences = [];

        if ($option->sourceType === 'own') {
            $sentences[] = 'این یکی از پکیج‌های اختصاصی ماست.';
        } elseif ($isBest && $option->totalPrice !== null) {
            $sentences[] = 'این گزینه در حال حاضر کمترین قیمت را در بین گزینه‌های مقایسه‌شده دارد.';
        }

        $boardSentence = $this->boardSentence($option);
        if ($boardSentence !== null) {
            $sentences[] = $boardSentence;
        }

        if (\in_array('Within your budget', $option->reasons, true)) {
            $sentences[] = 'با بودجه‌ای که گفتید هم‌خوانی دارد.';
        } elseif (\in_array('Above budget', $option->warnings, true)) {
            $sentences[] = 'قیمت آن کمی بیشتر از بودجه‌ای است که گفتید.';
        }

        if ($sentences === [] && $option->totalPrice !== null && $option->currency !== null) {
            $sentences[] = sprintf('این گزینه برای %d شب با قیمت %s %s ارائه شده است.', $option->nights, $option->totalPrice, $option->currency);
        }

        return implode(' ', $sentences);
    }

    private function boardSentence(TripOption $option): ?string
    {
        $hasBreakfast = $option->board !== null && stripos($option->board, 'breakfast') !== false;
        $review = $this->primaryReview($option);

        if ($hasBreakfast && $review !== null && $option->hotelName !== null) {
            return sprintf('این هتل صبحانه دارد و امتیاز %s از منبع بررسی‌شده گرفته است%s.', $this->scoreLabel($review), $this->reviewCountSuffix($review));
        }

        if ($hasBreakfast && $option->hotelName !== null) {
            return sprintf('هتل %s شامل صبحانه است.', $option->hotelName);
        }

        if ($review !== null && $option->hotelName !== null) {
            return sprintf('هتل %s امتیاز %s دارد%s.', $option->hotelName, $this->scoreLabel($review), $this->reviewCountSuffix($review));
        }

        return null;
    }

    private function scoreLabel(HotelRecommendationSourceContext $review): string
    {
        return $review->reviewScoreScale !== null
            ? sprintf('%s از %s', $review->reviewScore, $review->reviewScoreScale)
            : (string) $review->reviewScore;
    }

    private function reviewCountSuffix(HotelRecommendationSourceContext $review): string
    {
        return $review->reviewCount !== null ? sprintf(' (بر اساس %d نظر)', $review->reviewCount) : '';
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

    private function destinationLabel(TripSearchRequest $request): ?string
    {
        $city = $request->destinationCity;
        if ($city !== null) {
            return $city->getNameFa() ?? $city->getName();
        }

        $country = $request->destinationCountry();

        return $country?->getNameFa() ?? $country?->getName();
    }
}
