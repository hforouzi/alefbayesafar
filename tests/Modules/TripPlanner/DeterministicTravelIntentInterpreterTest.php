<?php

namespace App\Tests\Modules\TripPlanner;

use App\Modules\TripPlanner\Service\DeterministicTravelIntentInterpreter;
use App\Modules\TripPlanner\ValueObject\ConversationState;
use PHPUnit\Framework\TestCase;

class DeterministicTravelIntentInterpreterTest extends TestCase
{
    public function testNightsAndDestinationAreExtracted(): void
    {
        $update = (new DeterministicTravelIntentInterpreter())->interpret('۵ روز استانبول می‌خوام', new ConversationState());

        self::assertSame(5, $update->nights);
        self::assertSame('استانبول', $update->destinationCityName);
        self::assertTrue($update->recognized);
    }

    public function testBareCityReplyIsTreatedAsOriginWhenAwaitingOrigin(): void
    {
        $state = new ConversationState();
        $state->awaitingField = 'origin';

        $update = (new DeterministicTravelIntentInterpreter())->interpret('رشت', $state);

        self::assertSame('رشت', $update->originCityName);
        self::assertNull($update->destinationCityName);
    }

    public function testOriginAndDestinationCountryAreExtracted(): void
    {
        $update = (new DeterministicTravelIntentInterpreter())->interpret('از رشت می‌خوام برم ترکیه', new ConversationState());

        self::assertSame('رشت', $update->originCityName);
        self::assertSame('ترکیه', $update->destinationCityName);
    }

    public function testMonthAndFlexibleHintAreExtracted(): void
    {
        $update = (new DeterministicTravelIntentInterpreter())->interpret('تو مهر هر وقت ارزون‌تره', new ConversationState());

        self::assertSame('مهر', $update->monthHint);
        self::assertTrue($update->flexibleHint);
    }

    public function testAdultsAndChildrenAreExtracted(): void
    {
        $update = (new DeterministicTravelIntentInterpreter())->interpret('برای دو نفر با دو تا بچه', new ConversationState());

        self::assertSame(2, $update->adults);
        self::assertSame(2, $update->children);
    }

    public function testHotelStarPreferenceIsExtracted(): void
    {
        $update = (new DeterministicTravelIntentInterpreter())->interpret('هتل ۴ ستاره به بالا', new ConversationState());

        self::assertSame(4, $update->hotelStarPreference);
    }

    public function testBreakfastPreferenceIsExtracted(): void
    {
        $update = (new DeterministicTravelIntentInterpreter())->interpret('صبحانه داشته باشه', new ConversationState());

        self::assertTrue($update->breakfastPreferred);
    }

    public function testShoppingPurposeIsExtractedAndDoesNotLeakIntoDestinationGuess(): void
    {
        $update = (new DeterministicTravelIntentInterpreter())->interpret('۵ روز استانبول برای خرید', new ConversationState());

        self::assertSame('shopping', $update->purpose);
        self::assertSame('استانبول', $update->destinationCityName);
        self::assertSame(5, $update->nights);
    }

    public function testMessageWithoutPurposeLeavesPurposeNull(): void
    {
        $update = (new DeterministicTravelIntentInterpreter())->interpret('۵ روز استانبول می‌خوام', new ConversationState());

        self::assertNull($update->purpose);
    }

    public function testOpenDestinationIsExtracted(): void
    {
        $update = (new DeterministicTravelIntentInterpreter())->interpret('هر جا ارزون‌تره برای ۵ شب', new ConversationState());

        self::assertTrue($update->destinationOpen);
        self::assertSame(5, $update->nights);
    }

    public function testBetterHotelRefinementIsExtracted(): void
    {
        $update = (new DeterministicTravelIntentInterpreter())->interpret('هتل بهتر می‌خوام', new ConversationState());

        self::assertTrue($update->betterHotelRequested);
    }

    public function testCheaperRefinementIsExtracted(): void
    {
        $update = (new DeterministicTravelIntentInterpreter())->interpret('ارزون‌ترشو نشون بده', new ConversationState());

        self::assertTrue($update->cheaperRequested);
    }

    public function testAnotherCityRefinementIsExtracted(): void
    {
        $update = (new DeterministicTravelIntentInterpreter())->interpret('یه شهر دیگه تو ترکیه', new ConversationState());

        self::assertTrue($update->anotherCityRequested);
    }

    public function testWidenWindowRefinementIsExtracted(): void
    {
        $update = (new DeterministicTravelIntentInterpreter())->interpret('تاریخ رو بازتر کن', new ConversationState());

        self::assertTrue($update->widenWindowRequested);
    }

    public function testBudgetWithMillionMultiplierIsExtracted(): void
    {
        $update = (new DeterministicTravelIntentInterpreter())->interpret('بودجه‌م ۱۰۰ میلیون', new ConversationState());

        self::assertSame('100000000', $update->budget);
    }

    public function testOriginDestinationCountryNightsAndMonthAreExtractedTogether(): void
    {
        $update = (new DeterministicTravelIntentInterpreter())->interpret('از تهران می‌خوام برم ترکیه ۵ شب تو مهر', new ConversationState());

        self::assertSame('تهران', $update->originCityName);
        self::assertSame('ترکیه', $update->destinationCityName);
        self::assertSame(5, $update->nights);
        self::assertSame('مهر', $update->monthHint);
    }

    public function testAutumnSeasonIsRecognizedAsAMonthHint(): void
    {
        $update = (new DeterministicTravelIntentInterpreter())->interpret('تو پاییز', new ConversationState());

        self::assertSame('پاییز', $update->monthHint);
        self::assertNull($update->destinationCityName, 'the season word must not be misread as a place name');
    }

    public function testWheneverCheaperPhrasesAreRecognizedAsFlexibleHint(): void
    {
        $interpreter = new DeterministicTravelIntentInterpreter();

        foreach (['هر زمانی که ارزون‌تره', 'هر وقت ارزون‌تره', 'تاریخ مهم نیست', 'هر تاریخی', 'فرقی نداره'] as $message) {
            $update = $interpreter->interpret($message, new ConversationState());
            self::assertTrue($update->flexibleHint, \sprintf('"%s" should be recognized as a flexible-date hint', $message));
        }
    }

    public function testLeftoverWordDoesNotOverwriteAnAlreadyKnownDestination(): void
    {
        $state = new ConversationState();
        $state->destinationCityId = 42;
        $state->destinationCityName = 'استانبول';
        $state->awaitingField = 'date';

        $update = (new DeterministicTravelIntentInterpreter())->interpret('نه', $state);

        self::assertNull($update->destinationCityName);
    }

    public function testEmptyMessageIsNotRecognized(): void
    {
        $update = (new DeterministicTravelIntentInterpreter())->interpret('   ', new ConversationState());

        self::assertFalse($update->recognized);
    }
}
