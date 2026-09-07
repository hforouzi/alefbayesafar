<?php

namespace App\Tests\Modules\TripPlanner;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\TripPlanner\Enum\TripOptionType;
use App\Modules\TripPlanner\Enum\TripPlanStatus;
use App\Modules\TripPlanner\Service\DeterministicTravelRecommendationExplanationService;
use App\Modules\TripPlanner\ValueObject\HotelRecommendationContext;
use App\Modules\TripPlanner\ValueObject\HotelRecommendationSourceContext;
use App\Modules\TripPlanner\ValueObject\TripOption;
use App\Modules\TripPlanner\ValueObject\TripPlanResult;
use App\Modules\TripPlanner\ValueObject\TripSearchRequest;
use PHPUnit\Framework\TestCase;

class DeterministicTravelRecommendationExplanationServiceTest extends TestCase
{
    public function testProducesFriendlyPersianNoOptionsMessage(): void
    {
        $service = new DeterministicTravelRecommendationExplanationService();
        $result = new TripPlanResult(TripPlanStatus::NO_OPTIONS, [], ['No live tour was found for this period.'], []);

        $explanation = $service->explain($this->request(), $result);

        self::assertSame(
            'در حال حاضر برای Istanbul در این بازه گزینه مناسبی پیدا نشد. می‌توانید بازه تاریخ را بازتر کنید یا شهرهای دیگر همان کشور را بررسی کنید.',
            $explanation->overallText,
        );
        self::assertSame([], $explanation->perOptionText);
    }

    public function testOwnPackageIsExplainedAsOurOwnPackage(): void
    {
        $service = new DeterministicTravelRecommendationExplanationService();
        $option = $this->option(sourceType: 'own', totalPrice: '1490.00', rank: 1);
        $result = new TripPlanResult(TripPlanStatus::OPTIONS_FOUND, [$option], ['Trip options were built from available commercial inventory.'], []);

        $explanation = $service->explain($this->request(), $result);

        self::assertSame('این یکی از پکیج‌های اختصاصی ماست.', $explanation->forOption(1));
        self::assertStringContainsString('1490.00 EUR', $explanation->overallText);
    }

    public function testOverallTextAddsComparativePriceInsightWhenMultipleOptionsExist(): void
    {
        $service = new DeterministicTravelRecommendationExplanationService();
        $close = $this->option(sourceType: 'live_external', totalPrice: '690.00', rank: 1);
        $alsoClose = $this->option(sourceType: 'live_external', totalPrice: '720.00', rank: 2);
        $result = new TripPlanResult(TripPlanStatus::OPTIONS_FOUND, [$close, $alsoClose], [], []);

        $explanation = $service->explain($this->request(), $result);

        self::assertStringContainsString('بازه قیمتی مشابه', $explanation->overallText);
    }

    public function testOverallTextFlagsANoticeablePriceGapBetweenOptions(): void
    {
        $service = new DeterministicTravelRecommendationExplanationService();
        $cheap = $this->option(sourceType: 'live_external', totalPrice: '400.00', rank: 1);
        $expensive = $this->option(sourceType: 'live_external', totalPrice: '900.00', rank: 2);
        $result = new TripPlanResult(TripPlanStatus::OPTIONS_FOUND, [$cheap, $expensive], [], []);

        $explanation = $service->explain($this->request(), $result);

        self::assertStringContainsString('تفاوت محسوسی دارد', $explanation->overallText);
    }

    public function testLowestPricedExternalOptionIsFlaggedAsBestMatch(): void
    {
        $service = new DeterministicTravelRecommendationExplanationService();
        $option = $this->option(sourceType: 'live_external', totalPrice: '690.00', rank: 1);
        $result = new TripPlanResult(TripPlanStatus::OPTIONS_FOUND, [$option], ['Trip options were built from available commercial inventory.'], []);

        $explanation = $service->explain($this->request(), $result);

        self::assertSame('این گزینه در حال حاضر کمترین قیمت را در بین گزینه‌های مقایسه‌شده دارد.', $explanation->forOption(1));
    }

    public function testBreakfastWithReviewScoreIsExplainedFactually(): void
    {
        $service = new DeterministicTravelRecommendationExplanationService();
        $context = new HotelRecommendationContext(
            hotelName: 'Titanic City Taksim',
            canonicalMatched: false,
            stars: 4,
            sources: [
                new HotelRecommendationSourceContext(
                    source: 'LastSecond',
                    status: 'success',
                    reviewScore: '4.32',
                    reviewScoreScale: '5',
                    reviewCount: 36,
                ),
            ],
        );
        $option = $this->option(sourceType: 'live_external', totalPrice: '690.00', rank: 1, board: 'Breakfast (BB)', hotelName: 'Titanic City Taksim', context: $context);
        $result = new TripPlanResult(TripPlanStatus::OPTIONS_FOUND, [$option], ['Trip options were built from available commercial inventory.'], []);

        $explanation = $service->explain($this->request(), $result);

        self::assertSame(
            'این گزینه در حال حاضر کمترین قیمت را در بین گزینه‌های مقایسه‌شده دارد. این هتل صبحانه دارد و امتیاز 4.32 از 5 از منبع بررسی‌شده گرفته است (بر اساس 36 نظر).',
            $explanation->forOption(1),
        );
    }

    private function option(string $sourceType, ?string $totalPrice, int $rank, ?string $board = null, ?string $hotelName = null, ?HotelRecommendationContext $context = null): TripOption
    {
        return new TripOption(
            optionType: $sourceType === 'own' ? TripOptionType::OUR_TOUR : TripOptionType::EXTERNAL_TOUR,
            sourceType: $sourceType,
            sourceName: 'Test Source',
            title: 'Istanbul 5 Nights',
            currency: 'EUR',
            totalPrice: $totalPrice,
            components: [],
            departureDate: new \DateTimeImmutable('2026-10-01'),
            returnDate: new \DateTimeImmutable('2026-10-06'),
            nights: 5,
            completenessStatus: 'complete',
            budgetStatus: 'unknown',
            rankingScore: 0,
            reasons: [],
            warnings: [],
            destinationCity: 'Istanbul',
            hotelName: $hotelName,
            board: $board,
            hotelRecommendationContext: $context,
            rank: $rank,
        );
    }

    private function request(): TripSearchRequest
    {
        $country = (new Country())->setName('Turkey');
        $city = (new City())->setCountry($country)->setName('Istanbul')->setSlug('istanbul');

        return new TripSearchRequest(
            originAirport: null,
            originCity: null,
            destinationCity: $city,
            departureDate: new \DateTimeImmutable('2026-10-01'),
            returnDate: new \DateTimeImmutable('2026-10-06'),
            nights: 5,
            adults: 2,
        );
    }
}
