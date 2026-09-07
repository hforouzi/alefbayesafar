<?php

namespace App\Tests\Modules\TripPlanner;

use App\Modules\TripPlanner\Enum\TripOptionType;
use App\Modules\TripPlanner\Service\TravelFollowUpAnswerService;
use App\Modules\TripPlanner\ValueObject\ConversationState;
use App\Modules\TripPlanner\ValueObject\HotelRecommendationContext;
use App\Modules\TripPlanner\ValueObject\HotelRecommendationSourceContext;
use App\Modules\TripPlanner\ValueObject\TripOption;
use PHPUnit\Framework\TestCase;

class TravelFollowUpAnswerServiceTest extends TestCase
{
    public function testNoFollowUpIsDetectedWithoutExistingResults(): void
    {
        $service = new TravelFollowUpAnswerService();

        self::assertNull($service->detectIntent('کدوم ارزون‌تره؟', false));
    }

    public function testImperativeRefinementMessagesAreNotTreatedAsFollowUpQuestions(): void
    {
        $service = new TravelFollowUpAnswerService();

        self::assertNull($service->detectIntent('هتل بهتر می‌خوام', true));
        self::assertNull($service->detectIntent('ارزون‌ترشو نشون بده', true));
        self::assertNull($service->detectIntent('صبحانه داشته باشه', true));
    }

    public function testCheapestQuestionIsDetectedAndAnswered(): void
    {
        $service = new TravelFollowUpAnswerService();
        $cheap = $this->option(totalPrice: '600.00', currency: 'EUR', title: 'Cheap Tour', hotelName: 'Carton Hotel');
        $pricey = $this->option(totalPrice: '750.00', currency: 'EUR', title: 'Pricey Tour', hotelName: 'Taksim Town Hotel');

        $intent = $service->detectIntent('کدوم ارزون‌تره؟', true);
        self::assertSame('cheapest', $intent);

        $answer = $service->answer($intent, [$pricey, $cheap], new ConversationState());

        self::assertStringContainsString('Carton Hotel', $answer);
        self::assertStringContainsString('600.00 EUR', $answer);
        self::assertStringContainsString('Taksim Town Hotel', $answer);
        self::assertStringContainsString('150.00 EUR', $answer);
    }

    public function testCheapestQuestionWithMixedCurrenciesIsHonestAboutIt(): void
    {
        $service = new TravelFollowUpAnswerService();
        $eur = $this->option(totalPrice: '600.00', currency: 'EUR', title: 'Euro Tour');
        $irr = $this->option(totalPrice: '50000000', currency: 'IRR', title: 'Rial Tour');

        $answer = $service->answer('cheapest', [$eur, $irr], new ConversationState());

        self::assertStringContainsString('واحدهای پولی متفاوت', $answer);
    }

    public function testBestReviewedQuestionComparesNormalizedScoresAcrossScales(): void
    {
        $service = new TravelFollowUpAnswerService();
        $lowerScale = $this->option(hotelName: 'Basic Hotel', context: $this->review('8.0', '10', 20, 'ProviderA'));
        $higherRelative = $this->option(hotelName: 'Great Hotel', context: $this->review('4.5', '5', 40, 'ProviderB'));

        $intent = $service->detectIntent('کدوم امتیاز بهتری داره؟', true);
        self::assertSame('best_reviewed', $intent);

        $answer = $service->answer($intent, [$lowerScale, $higherRelative], new ConversationState());

        self::assertStringContainsString('Great Hotel', $answer);
        self::assertStringContainsString('4.5 از 5', $answer);
    }

    public function testBreakfastQuestionListsOnlyOptionsThatHaveIt(): void
    {
        $service = new TravelFollowUpAnswerService();
        $withBreakfast = $this->option(hotelName: 'Breakfast Hotel', board: 'Breakfast (BB)');
        $withoutBreakfast = $this->option(hotelName: 'Room Only Hotel', board: 'Room Only');

        $intent = $service->detectIntent('صبحانه داره؟', true);
        self::assertSame('breakfast', $intent);

        $answer = $service->answer($intent, [$withBreakfast, $withoutBreakfast], new ConversationState());

        self::assertStringContainsString('Breakfast Hotel', $answer);
        self::assertStringNotContainsString('Room Only Hotel', $answer);
    }

    public function testFamilyQuestionHonestlyAdmitsMissingDataWhenNothingIsKnown(): void
    {
        $service = new TravelFollowUpAnswerService();
        $plain = $this->option(hotelName: 'Plain Hotel');

        $answer = $service->answer('family', [$plain], new ConversationState());

        self::assertStringContainsString('اطلاعات کافی', $answer);
    }

    public function testWhichBetterComparesPriceAndReviewWhenTheyDisagree(): void
    {
        $service = new TravelFollowUpAnswerService();
        $cheap = $this->option(totalPrice: '600.00', currency: 'EUR', hotelName: 'Carton Hotel');
        $reviewed = $this->option(totalPrice: '750.00', currency: 'EUR', hotelName: 'Taksim Town Hotel', context: $this->review('4.8', '5', 100, 'ProviderA'));

        $intent = $service->detectIntent('کدوم بهتره؟', true);
        self::assertSame('which_better', $intent);

        $answer = $service->answer($intent, [$cheap, $reviewed], new ConversationState());

        self::assertStringContainsString('Carton Hotel', $answer);
        self::assertStringContainsString('Taksim Town Hotel', $answer);
    }

    public function testDateFitPicksTheOptionClosestToTheRequestedWindow(): void
    {
        $service = new TravelFollowUpAnswerService();
        $near = $this->option(hotelName: 'Near Date Hotel', departureDate: new \DateTimeImmutable('2026-10-05'));
        $far = $this->option(hotelName: 'Far Date Hotel', departureDate: new \DateTimeImmutable('2026-11-20'));

        $state = new ConversationState();
        $state->windowStart = '2026-10-01';
        $state->windowEnd = '2026-10-10';

        $intent = $service->detectIntent('کدوم به تاریخ من نزدیک‌تره؟', true);
        self::assertSame('date_fit', $intent);

        $answer = $service->answer($intent, [$near, $far], $state);

        self::assertStringContainsString('Near Date Hotel', $answer);
        self::assertStringNotContainsString('Far Date Hotel', $answer);
    }

    private function option(
        ?string $totalPrice = null,
        ?string $currency = 'EUR',
        string $title = 'Istanbul 5 Nights',
        ?string $hotelName = null,
        ?string $board = null,
        ?HotelRecommendationContext $context = null,
        ?\DateTimeImmutable $departureDate = null,
    ): TripOption {
        return new TripOption(
            optionType: TripOptionType::EXTERNAL_TOUR,
            sourceType: 'live_external',
            sourceName: 'Test Source',
            title: $title,
            currency: $currency,
            totalPrice: $totalPrice,
            components: [],
            departureDate: $departureDate ?? new \DateTimeImmutable('2026-10-01'),
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
            rank: 1,
        );
    }

    private function review(string $score, string $scale, int $count, string $source): HotelRecommendationContext
    {
        return new HotelRecommendationContext(
            hotelName: 'Hotel',
            canonicalMatched: false,
            sources: [
                new HotelRecommendationSourceContext(
                    source: $source,
                    status: 'success',
                    reviewScore: $score,
                    reviewScoreScale: $scale,
                    reviewCount: $count,
                ),
            ],
        );
    }
}
