<?php

namespace App\Tests\Modules\TripPlanner;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Provider\DestinationInsightProviderInterface;
use App\Modules\Destination\ValueObject\DestinationInsight;
use App\Modules\Destination\ValueObject\DestinationInsightResult;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\Tour\Entity\ExternalTourOffer;
use App\Modules\Tour\Enum\TourAvailabilityStatus;
use App\Modules\TripPlanner\Service\ConversationalTravelPlanningService;
use App\Modules\TripPlanner\ValueObject\ConversationState;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Every fixture city/country gets a suffix baked directly into its Persian
 * (nameFa) name, and every chat message in these tests references that same
 * suffixed Persian text. CityRepository/CountryRepository search is a plain
 * substring match against the whole catalog (as it is in production), so
 * without a unique-per-test name here, one test's fixture could resolve to
 * another test's city/offer since this suite shares one persistent test DB.
 */
class ConversationalTravelPlanningServiceTest extends KernelTestCase
{
    public function testFiveDaysIstanbulAsksForOrigin(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $em->flush();

        $state = new ConversationState();
        $state = $this->service()->handleMessage($state, '۵ روز ' . $catalog['istanbulFa'] . ' می‌خوام');

        self::assertSame(5, $state->nights);
        self::assertSame('origin', $state->awaitingField);
        self::assertSame('assistant', $state->transcript[array_key_last($state->transcript)]['role']);
        self::assertStringContainsString('کدام شهر', $state->transcript[array_key_last($state->transcript)]['text']);
        self::assertNull($state->lastResultOptions);
    }

    public function testFollowUpOriginRetainsDestinationAndAsksForDate(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $em->flush();

        $service = $this->service();
        $state = new ConversationState();
        $state = $service->handleMessage($state, '۵ روز ' . $catalog['istanbulFa'] . ' می‌خوام');
        $state = $service->handleMessage($state, $catalog['rashtFa']);

        self::assertSame($catalog['rashtFa'], $state->originCityName);
        self::assertSame($catalog['istanbulFa'], $state->destinationCityName);
        self::assertSame(5, $state->nights);
        self::assertSame('date', $state->awaitingField);
        self::assertStringContainsString('تاریخ', $state->transcript[array_key_last($state->transcript)]['text']);
    }

    public function testCompletingDateInfoRunsLiveSearchAndRendersOptions(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $offerTitle = 'Chat Istanbul Offer ' . self::suffix();
        $this->offer($em, $catalog['istanbul'], $offerTitle, '2026-10-01', '2026-10-06', 5, '690.00', 'Chat Hotel');
        $em->flush();

        $service = $this->service();
        $state = new ConversationState();
        $state = $service->handleMessage($state, '۵ روز ' . $catalog['istanbulFa'] . ' می‌خوام');
        $state = $service->handleMessage($state, $catalog['rashtFa']);
        $state = $service->handleMessage($state, 'تو مهر هر وقت ارزون‌تره');

        self::assertNull($state->awaitingField);
        self::assertTrue($state->searchAttempted);
        self::assertNotEmpty($state->lastResultOptions);
        self::assertSame($offerTitle, $state->lastResultOptions[0]->title);
        self::assertNotNull($state->lastExplanationOverall);
    }

    public function testCountryOnlyDestinationDoesNotAskForCity(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $em->flush();

        $service = $this->service();
        $state = new ConversationState();
        $state = $service->handleMessage($state, 'از ' . $catalog['tehranFa'] . ' می‌خوام برم ' . $catalog['turkeyFa'] . ' ۵ شب تو مهر');

        self::assertSame($catalog['tehranFa'], $state->originCityName);
        self::assertSame($catalog['turkeyFa'], $state->destinationCountryName);
        self::assertNull($state->awaitingField);
        self::assertTrue($state->searchAttempted);
    }

    public function testBetterHotelRefinementRetainsPreviousContext(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $this->offer($em, $catalog['istanbul'], 'Refine Istanbul Offer ' . self::suffix(), '2026-10-01', '2026-10-06', 5, '690.00', 'Refine Hotel');
        $em->flush();

        $service = $this->service();
        $state = new ConversationState();
        $state = $service->handleMessage($state, '۵ روز ' . $catalog['istanbulFa'] . ' می‌خوام');
        $state = $service->handleMessage($state, $catalog['rashtFa']);
        $state = $service->handleMessage($state, 'تو مهر هر وقت ارزون‌تره');
        $originBeforeRefinement = $state->originCityName;
        $destinationBeforeRefinement = $state->destinationCityName;

        $state = $service->handleMessage($state, 'هتل بهتر می‌خوام');

        self::assertSame($originBeforeRefinement, $state->originCityName);
        self::assertSame($destinationBeforeRefinement, $state->destinationCityName);
        self::assertSame(4, $state->hotelStarPreference);
        self::assertNull($state->awaitingField);
        self::assertNotEmpty($state->lastResultOptions);
    }

    public function testCheaperRefinementRetainsPreviousContext(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $this->offer($em, $catalog['istanbul'], 'Cheaper Istanbul Offer ' . self::suffix(), '2026-10-01', '2026-10-06', 5, '690.00', 'Cheaper Hotel');
        $em->flush();

        $service = $this->service();
        $state = new ConversationState();
        $state = $service->handleMessage($state, '۵ روز ' . $catalog['istanbulFa'] . ' می‌خوام');
        $state = $service->handleMessage($state, $catalog['rashtFa']);
        $state = $service->handleMessage($state, 'تو مهر هر وقت ارزون‌تره');

        $state = $service->handleMessage($state, 'ارزون‌ترشو نشون بده');

        self::assertSame($catalog['rashtFa'], $state->originCityName);
        self::assertSame($catalog['istanbulFa'], $state->destinationCityName);
        self::assertNotEmpty($state->lastResultOptions);
    }

    public function testNoResultsProducesConversationalMessageWithoutBackendVocabulary(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $em->flush();

        $service = $this->service();
        $state = new ConversationState();
        $state = $service->handleMessage($state, '۵ روز ' . $catalog['istanbulFa'] . ' می‌خوام');
        $state = $service->handleMessage($state, $catalog['rashtFa']);
        $state = $service->handleMessage($state, 'تو مهر هر وقت ارزون‌تره');

        self::assertSame([], $state->lastResultOptions);
        $lastMessage = $state->transcript[array_key_last($state->transcript)]['text'];
        self::assertStringContainsString('گزینه مناسبی پیدا نکردم', $lastMessage);
        foreach (['no_options', 'provider_error', 'cached_external', 'raw'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $lastMessage);
        }
    }

    /**
     * Reproduces the exact reported bug flow:
     * "یه سفر ارزون از رشت می‌خوام" → "ترکیه" → "تو پاییز" → "هر زمانی که ارزون‌تره"
     * must reach TravelPlanningService instead of looping on the date question.
     */
    public function testCheapRashtToTurkeyAutumnWheneverCheaperReachesLiveSearch(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $offerTitle = 'Autumn Cheap Offer ' . self::suffix();
        $this->offer($em, $catalog['istanbul'], $offerTitle, '2026-10-05', '2026-10-10', 5, '450.00', 'Autumn Hotel');
        $em->flush();

        $service = $this->service();
        $state = new ConversationState();

        // A) "یه سفر ارزون از رشت می‌خوام" — retains origin, records the cheapest goal.
        $state = $service->handleMessage($state, 'یه سفر ارزون از ' . $catalog['rashtFa'] . ' می‌خوام');
        self::assertSame($catalog['rashtFa'], $state->originCityName);
        self::assertSame('cheapest', $state->goal);
        self::assertSame('destination', $state->awaitingField);
        self::assertNull($state->lastResultOptions);

        // B) "ترکیه" — keeps origin + goal, adds the destination country.
        $state = $service->handleMessage($state, $catalog['turkeyFa']);
        self::assertSame($catalog['rashtFa'], $state->originCityName);
        self::assertSame('cheapest', $state->goal);
        self::assertSame($catalog['turkeyFa'], $state->destinationCountryName);
        self::assertSame('date', $state->awaitingField);

        // C) "تو پاییز" — produces a concrete flexible window instead of leaving the date unknown.
        $state = $service->handleMessage($state, 'تو پاییز');
        self::assertSame('flexible', $state->dateMode);
        self::assertNotNull($state->windowStart);
        self::assertNotNull($state->windowEnd);
        self::assertSame($catalog['rashtFa'], $state->originCityName, 'origin must survive the date-only follow-up');
        self::assertSame($catalog['turkeyFa'], $state->destinationCountryName, 'destination must survive the date-only follow-up');
        $windowStartAfterAutumn = $state->windowStart;
        $windowEndAfterAutumn = $state->windowEnd;
        $assistantMessagesAfterC = \count(array_filter($state->transcript, static fn (array $m): bool => $m['role'] === 'assistant'));

        // D) "هر زمانی که ارزون‌تره" — must NOT ask the date question again, and must not discard the known window.
        $state = $service->handleMessage($state, 'هر زمانی که ارزون‌تره');
        self::assertSame($windowStartAfterAutumn, $state->windowStart, 'the autumn window must not be reset by "whenever is cheaper"');
        self::assertSame($windowEndAfterAutumn, $state->windowEnd);
        self::assertNull($state->awaitingField, 'no field should still be blocking the search');
        $lastAssistantMessage = null;
        foreach (array_reverse($state->transcript) as $entry) {
            if ($entry['role'] === 'assistant') {
                $lastAssistantMessage = $entry['text'];
                break;
            }
        }
        self::assertNotNull($lastAssistantMessage);
        self::assertStringNotContainsString('تاریخ خاصی مدنظرته', $lastAssistantMessage, 'the date question must not repeat once a window is already known');

        // E) TravelPlanningService must actually have run by now, with a real matching result.
        self::assertTrue($state->searchAttempted);
        self::assertNotNull($state->lastResultOptions);
        self::assertNotEmpty($state->lastResultOptions);
        self::assertSame($offerTitle, $state->lastResultOptions[0]->title);
    }

    public function testUnhelpfulRepliesDoNotRepeatTheExactSameClarificationQuestionForever(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $em->flush();

        $service = $this->service();
        $state = new ConversationState();
        $state = $service->handleMessage($state, 'یه سفر ارزون از ' . $catalog['rashtFa'] . ' می‌خوام');
        $state = $service->handleMessage($state, $catalog['turkeyFa']);

        self::assertSame('date', $state->awaitingField);
        $firstQuestion = $state->transcript[array_key_last($state->transcript)]['text'];
        self::assertSame([], $state->suggestedReplies);

        // An unrecognized reply cannot resolve the date field, so the same
        // field is asked about again — but not with the identical wording.
        $state = $service->handleMessage($state, 'نه');

        self::assertSame('date', $state->awaitingField);
        $secondQuestion = $state->transcript[array_key_last($state->transcript)]['text'];
        self::assertNotSame($firstQuestion, $secondQuestion, 'the exact same clarification question must not repeat');
        self::assertNotEmpty($state->suggestedReplies, 'a repeated field should offer quick-choice suggestions instead of looping');
    }

    public function testFollowUpMessagesNeverEraseKnownStateFieldsTheyDoNotMention(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $em->flush();

        $service = $this->service();
        $state = new ConversationState();
        $state = $service->handleMessage($state, 'یه سفر ارزون از ' . $catalog['rashtFa'] . ' می‌خوام');
        $state = $service->handleMessage($state, $catalog['turkeyFa']);
        $state = $service->handleMessage($state, 'تو پاییز');

        $originId = $state->originCityId;
        $destinationCountryId = $state->destinationCountryId;
        $windowStart = $state->windowStart;
        $windowEnd = $state->windowEnd;

        // A message about something unrelated must not null out anything already known.
        $state = $service->handleMessage($state, 'صبحانه داشته باشه');

        self::assertSame($originId, $state->originCityId);
        self::assertSame($destinationCountryId, $state->destinationCountryId);
        self::assertSame($windowStart, $state->windowStart);
        self::assertSame($windowEnd, $state->windowEnd);
        self::assertTrue($state->breakfastPreferred);
    }

    /**
     * Reproduces the exact reported public /build mismatch:
     * "۵ روز استانبول تو مهر" → "تهران" must retain Istanbul + Mehr + 5 nights,
     * resolve Tehran as origin (with its international airport resolved
     * internally, never asked from the user), and actually reach
     * TravelPlanningService — matching the known-good admin Tehran/IKA →
     * Istanbul request.
     */
    public function testFiveDaysIstanbulInMehrThenTehranReachesLiveSearchWithInternalAirportResolution(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $ika = (new Airport())->setCity($catalog['tehran'])->setName('Imam Khomeini International Airport ' . self::suffix())->setIataCode($this->uniqueIata($em));
        $em->persist($ika);
        $offerTitle = 'Mehr Istanbul Offer ' . self::suffix();
        $this->offer($em, $catalog['istanbul'], $offerTitle, '2026-09-28', '2026-10-03', 5, '520.00', 'Mehr Hotel', $ika);
        $em->flush();

        $service = $this->service();
        $state = new ConversationState();

        // Message 1: destination + nights + month, no origin yet.
        $state = $service->handleMessage($state, '۵ روز ' . $catalog['istanbulFa'] . ' تو مهر');
        self::assertSame($catalog['istanbulFa'], $state->destinationCityName);
        self::assertSame(5, $state->nights);
        self::assertSame('flexible', $state->dateMode);
        self::assertNotNull($state->windowStart);
        self::assertNotNull($state->windowEnd);
        self::assertSame('origin', $state->awaitingField);
        self::assertNull($state->originCityId);

        $destinationCityIdAfterMsg1 = $state->destinationCityId;
        $windowStartAfterMsg1 = $state->windowStart;
        $windowEndAfterMsg1 = $state->windowEnd;

        // Message 2: "تهران" — must resolve as origin without erasing anything from message 1.
        $state = $service->handleMessage($state, $catalog['tehranFa']);

        self::assertSame($catalog['tehranFa'], $state->originCityName, 'Tehran must resolve to a real city, not remain unrecognized');
        self::assertNotNull($state->originCityId);
        self::assertSame($destinationCityIdAfterMsg1, $state->destinationCityId, 'Istanbul must survive the origin follow-up');
        self::assertSame($catalog['istanbulFa'], $state->destinationCityName);
        self::assertSame(5, $state->nights, 'nights must remain 5');
        self::assertSame($windowStartAfterMsg1, $state->windowStart, 'the Mehr window must survive the origin follow-up');
        self::assertSame($windowEndAfterMsg1, $state->windowEnd);
        self::assertNull($state->awaitingField, 'nothing should still be blocking the search — the user must never be asked for an airport');

        self::assertTrue($state->searchAttempted, 'TravelPlanningService must actually have run');
        self::assertNotNull($state->lastResultOptions);
        self::assertNotEmpty($state->lastResultOptions, 'a matching stored offer exists inside the Mehr window and must be found');
        self::assertSame($offerTitle, $state->lastResultOptions[0]->title);
    }

    /**
     * Regression coverage for the reported "Tehran -> Turkey/Istanbul live
     * search" mismatch: proves the public conversational path resolves the
     * exact same structured fields the known-good admin LastSecond request
     * uses (Tehran origin city -> IKA airport auto-resolved, Turkey as a
     * country-only destination, flexible window, 5 nights, 2 adults) and
     * that TravelPlanningService actually executes and finds a real,
     * stored match — not a fabricated result.
     */
    public function testTehranToTurkeyCountryOnlyReachesLiveSearchWithSameFieldsAsAdmin(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $ika = (new Airport())->setCity($catalog['tehran'])->setName('Imam Khomeini International Airport ' . self::suffix())->setIataCode($this->uniqueIata($em));
        $em->persist($ika);
        $offerTitle = 'Mehr Turkey Offer ' . self::suffix();
        $this->offer($em, $catalog['istanbul'], $offerTitle, '2026-09-28', '2026-10-03', 5, '480.00', 'Mehr Turkey Hotel', $ika, 'IRR');
        $em->flush();

        $service = $this->service();
        $state = new ConversationState();
        $state = $service->handleMessage($state, 'از ' . $catalog['tehranFa'] . ' می‌خوام برم ' . $catalog['turkeyFa'] . ' ۵ شب تو مهر');

        // Same structured fields the admin Tehran/IKA -> Istanbul request uses.
        self::assertSame($catalog['tehranFa'], $state->originCityName);
        self::assertNotNull($state->originCityId);
        self::assertNull($state->destinationCityId, 'destination stays country-only, exactly as the user asked');
        self::assertSame($catalog['turkeyFa'], $state->destinationCountryName);
        self::assertSame(5, $state->nights);
        self::assertSame('flexible', $state->dateMode);
        self::assertNotNull($state->windowStart);
        self::assertNotNull($state->windowEnd);
        self::assertSame(2, $state->adults);
        self::assertSame(0, $state->children);
        self::assertNull($state->awaitingField, 'no field — especially not an airport — should still be blocking the search');

        self::assertTrue($state->searchAttempted, 'TravelPlanningService must actually have run, matching the admin path');
        self::assertNotNull($state->lastResultOptions);
        self::assertNotEmpty($state->lastResultOptions, 'a matching stored offer inside the Mehr window exists and must be found via country-only discovery, same as admin');
        self::assertSame($offerTitle, $state->lastResultOptions[0]->title);
        self::assertSame('IRR', $state->lastResultOptions[0]->currency);
    }

    public function testChangingOriginFromRashtToTehranUpdatesOnlyOrigin(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $em->flush();

        $service = $this->service();
        $state = new ConversationState();
        $state = $service->handleMessage($state, '۵ روز ' . $catalog['istanbulFa'] . ' تو مهر');
        $state = $service->handleMessage($state, $catalog['rashtFa']);

        self::assertSame($catalog['rashtFa'], $state->originCityName);
        $destinationCityId = $state->destinationCityId;
        $nights = $state->nights;
        $windowStart = $state->windowStart;
        $windowEnd = $state->windowEnd;

        // The user changes their mind about where they're leaving from.
        $state = $service->handleMessage($state, $catalog['tehranFa']);

        self::assertSame($catalog['tehranFa'], $state->originCityName, 'origin must switch to Tehran');
        self::assertSame($destinationCityId, $state->destinationCityId, 'destination must be untouched by an origin-only change');
        self::assertSame($nights, $state->nights, 'nights must be untouched by an origin-only change');
        self::assertSame($windowStart, $state->windowStart, 'window must be untouched by an origin-only change');
        self::assertSame($windowEnd, $state->windowEnd);
    }

    public function testDefaultCurrencyIsIrrForBudgetComparison(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $this->offer($em, $catalog['istanbul'], 'IRR Budget Offer ' . self::suffix(), '2026-10-05', '2026-10-10', 5, '450.00', 'Budget Hotel', null, 'IRR');
        $em->flush();

        $service = $this->service();
        $state = new ConversationState();
        $state = $service->handleMessage($state, 'یه سفر ارزون از ' . $catalog['rashtFa'] . ' می‌خوام');
        $state = $service->handleMessage($state, $catalog['turkeyFa']);
        $state->budget = '500.00';
        $state = $service->handleMessage($state, 'تو پاییز');

        self::assertNotEmpty($state->lastResultOptions);
        self::assertSame('IRR', $state->lastResultOptions[0]->currency);
        self::assertSame(
            'within_budget',
            $state->lastResultOptions[0]->budgetStatus,
            'the public default preferred currency must be IRR so an IRR-priced offer can be matched against the stated budget',
        );
    }

    public function testProviderEurOfferCurrencyIsNeverRelabeledAsIrr(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $this->offer($em, $catalog['istanbul'], 'EUR Offer ' . self::suffix(), '2026-10-05', '2026-10-10', 5, '450.00', 'Euro Hotel', null, 'EUR');
        $em->flush();

        $service = $this->service();
        $state = new ConversationState();
        $state = $service->handleMessage($state, 'یه سفر ارزون از ' . $catalog['rashtFa'] . ' می‌خوام');
        $state = $service->handleMessage($state, $catalog['turkeyFa']);
        $state->budget = '500.00';
        $state = $service->handleMessage($state, 'تو پاییز');

        self::assertNotEmpty($state->lastResultOptions);
        self::assertSame('EUR', $state->lastResultOptions[0]->currency, 'a EUR-priced offer must never be relabeled or displayed as IRR');
        self::assertSame(
            'unknown',
            $state->lastResultOptions[0]->budgetStatus,
            'budget comparison must not fake-convert currencies — EUR against a stated IRR-default budget must stay unknown, not silently pass',
        );
    }

    public function testFollowUpQuestionAfterResultsAnswersContextuallyWithoutRepeatingSummary(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $this->offer($em, $catalog['istanbul'], 'Follow-up Cheap Offer ' . self::suffix(), '2026-10-01', '2026-10-06', 5, '600.00', 'Cheap Follow-up Hotel');
        $this->offer($em, $catalog['istanbul'], 'Follow-up Pricey Offer ' . self::suffix(), '2026-10-01', '2026-10-06', 5, '750.00', 'Pricey Follow-up Hotel');
        $em->flush();

        $service = $this->service();
        $state = new ConversationState();
        $state = $service->handleMessage($state, '۵ روز ' . $catalog['istanbulFa'] . ' می‌خوام');
        $state = $service->handleMessage($state, $catalog['rashtFa']);
        $state = $service->handleMessage($state, 'تو مهر هر وقت ارزون‌تره');

        self::assertNotEmpty($state->lastResultOptions);
        $optionsBeforeFollowUp = $state->lastResultOptions;
        $searchAttemptedBefore = $state->searchAttempted;

        $state = $service->handleMessage($state, 'کدوم ارزون‌تره؟');

        $lastMessage = $state->transcript[array_key_last($state->transcript)]['text'];
        self::assertSame('assistant', $state->transcript[array_key_last($state->transcript)]['role']);
        self::assertStringNotContainsString('گزینه مناسب پیدا کردم', $lastMessage, 'a follow-up question must not repeat the original generic summary');
        self::assertStringContainsString('ارزان‌ترین', $lastMessage);
        self::assertSame($optionsBeforeFollowUp, $state->lastResultOptions, 'a follow-up question must not trigger a new search');
        self::assertSame($searchAttemptedBefore, $state->searchAttempted);
    }

    public function testShoppingPurposeFetchesRealDestinationInsightsAndAdvisorMentionsThemHonestly(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $this->offer($em, $catalog['istanbul'], 'Shopping Trip Offer ' . self::suffix(), '2026-10-01', '2026-10-06', 5, '690.00', 'Shopping Trip Hotel');
        $em->flush();

        self::getContainer()->set(DestinationInsightProviderInterface::class, $this->fakeInsightProvider([
            new DestinationInsight(title: 'Istinye Park', category: 'shopping', city: $catalog['istanbulFa'], source: 'Tripadvisor', rating: '4.5', reviewCount: 1234, sourceUrl: 'https://tripadvisor.example/istinye-park'),
            new DestinationInsight(title: 'Grand Bazaar', category: 'shopping', city: $catalog['istanbulFa'], source: 'Tripadvisor', rating: '4.6', reviewCount: 5000, sourceUrl: 'https://tripadvisor.example/grand-bazaar'),
        ]));

        $service = $this->service();
        $state = new ConversationState();
        $state = $service->handleMessage($state, '۵ روز ' . $catalog['istanbulFa'] . ' برای خرید');
        self::assertSame('shopping', $state->travelPurpose);
        $state = $service->handleMessage($state, $catalog['rashtFa']);
        $state = $service->handleMessage($state, 'تو مهر هر وقت ارزون‌تره');

        self::assertNotEmpty($state->lastResultOptions, 'the real tour offer must still be found alongside the purpose');
        self::assertCount(2, $state->lastDestinationInsights);
        self::assertSame('Istinye Park', $state->lastDestinationInsights[0]->title);

        $lastAssistantMessage = null;
        foreach (array_reverse($state->transcript) as $entry) {
            if ($entry['role'] === 'assistant') {
                $lastAssistantMessage = $entry['text'];
                break;
            }
        }
        self::assertNotNull($lastAssistantMessage);
        self::assertStringContainsString('Istinye Park', $lastAssistantMessage, 'the advisor must mention real destination insights, not just the generic summary');

        // Follow-up: "کجا برای خرید برم؟" must answer from the same real insights without repeating the generic summary.
        $state = $service->handleMessage($state, 'کجا برای خرید برم؟');
        $followUpAnswer = $state->transcript[array_key_last($state->transcript)]['text'];
        self::assertStringContainsString('Istinye Park', $followUpAnswer);
        self::assertStringContainsString('Grand Bazaar', $followUpAnswer);
        self::assertStringNotContainsString('گزینه مناسب پیدا کردم', $followUpAnswer);
    }

    public function testNoExactTourResultStillShowsRealDestinationInsightsWhenPurposeIsKnown(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $em->flush();

        self::getContainer()->set(DestinationInsightProviderInterface::class, $this->fakeInsightProvider([
            new DestinationInsight(title: 'Istinye Park', category: 'shopping', city: $catalog['istanbulFa'], source: 'Tripadvisor', rating: '4.5', reviewCount: 1234),
        ]));

        $service = $this->service();
        $state = new ConversationState();
        $state = $service->handleMessage($state, '۵ روز ' . $catalog['istanbulFa'] . ' برای خرید');
        $state = $service->handleMessage($state, $catalog['rashtFa']);
        $state = $service->handleMessage($state, 'تو مهر هر وقت ارزون‌تره');

        self::assertSame([], $state->lastResultOptions, 'no commercial offer exists for this fixture, so the exact search must genuinely find nothing');
        self::assertNotEmpty($state->lastDestinationInsights, 'the advisor must still surface real destination insights when no exact tour match exists');

        $lastAssistantMessage = $state->transcript[array_key_last($state->transcript)]['text'];
        self::assertStringNotContainsString('690.00', $lastAssistantMessage, 'no price may ever be fabricated when no real offer exists');
    }

    /**
     * @param DestinationInsight[] $insights
     */
    private function fakeInsightProvider(array $insights): DestinationInsightProviderInterface
    {
        return new class($insights) implements DestinationInsightProviderInterface {
            /** @param DestinationInsight[] $insights */
            public function __construct(private readonly array $insights)
            {
            }

            public function getCode(): string
            {
                return 'fake';
            }

            public function search(string $cityName, ?string $countryName, string $category, int $limit): DestinationInsightResult
            {
                return DestinationInsightResult::success($this->insights);
            }
        };
    }

    private function service(): ConversationalTravelPlanningService
    {
        return self::getContainer()->get(ConversationalTravelPlanningService::class);
    }

    /**
     * @return array{rasht: City, istanbul: City, tehran: City, rashtFa: string, istanbulFa: string, tehranFa: string, turkeyFa: string}
     */
    private function catalog(EntityManagerInterface $em): array
    {
        $suffix = self::suffix();
        $iranCountry = (new Country())->setName('Iran ' . $suffix);
        $turkeyFa = 'ترکیه' . $suffix;
        $turkeyCountry = (new Country())->setName('Turkey ' . $suffix)->setNameFa($turkeyFa);
        $rashtFa = 'رشت' . $suffix;
        $rasht = (new City())->setCountry($iranCountry)->setName('Rasht ' . $suffix)->setNameFa($rashtFa)->setSlug('chat-rasht-' . strtolower($suffix));
        $tehranFa = 'تهران' . $suffix;
        $tehran = (new City())->setCountry($iranCountry)->setName('Tehran ' . $suffix)->setNameFa($tehranFa)->setSlug('chat-tehran-' . strtolower($suffix));
        $istanbulFa = 'استانبول' . $suffix;
        $istanbul = (new City())->setCountry($turkeyCountry)->setName('Istanbul ' . $suffix)->setNameFa($istanbulFa)->setSlug('chat-istanbul-' . strtolower($suffix));

        foreach ([$iranCountry, $turkeyCountry, $rasht, $tehran, $istanbul] as $entity) {
            $em->persist($entity);
        }

        return [
            'rasht' => $rasht,
            'istanbul' => $istanbul,
            'tehran' => $tehran,
            'rashtFa' => $rashtFa,
            'istanbulFa' => $istanbulFa,
            'tehranFa' => $tehranFa,
            'turkeyFa' => $turkeyFa,
        ];
    }

    private function offer(EntityManagerInterface $em, City $destination, string $title, string $departure, string $return, int $nights, string $price, string $hotelName, ?Airport $originAirport = null, string $currency = 'EUR'): void
    {
        $source = (new SearchSource())
            ->setName('Chat Tour Source ' . self::suffix())
            ->setDomain('example.test')
            ->setProvider('test')
            ->setProviderType(SearchSourceProviderType::MANUAL)
            ->setCapabilities([SearchSource::CAPABILITY_TOUR])
            ->setEnabled(true);
        $offer = (new ExternalTourOffer())
            ->setSearchSource($source)
            ->setProviderCode('test')
            ->setTitle($title)
            ->setOriginAirport($originAirport)
            ->setDestinationCity($destination)
            ->setDestinationText($destination->getName())
            ->setDepartureDate(new \DateTimeImmutable($departure))
            ->setReturnDate(new \DateTimeImmutable($return))
            ->setNights($nights)
            ->setHotelName($hotelName)
            ->setBoardType('breakfast')
            ->setAdults(2)
            ->setChildren(0)
            ->setInfants(0)
            ->setCurrency($currency)
            ->setTotalPrice($price)
            ->setAvailabilityStatus(TourAvailabilityStatus::AVAILABLE)
            ->setFetchedAt(new \DateTimeImmutable('-1 hour'))
            ->setExpiresAt(new \DateTimeImmutable('+5 hours'))
            ->setMetadata([]);
        $em->persist($source);
        $em->persist($offer);
    }

    private static function suffix(): string
    {
        $letters = '';
        for ($index = 0; $index < 8; ++$index) {
            $letters .= chr(random_int(65, 90));
        }

        return $letters;
    }

    private function uniqueIata(EntityManagerInterface $em): string
    {
        do {
            $code = chr(random_int(65, 90)) . chr(random_int(65, 90)) . chr(random_int(65, 90));
        } while ($em->getRepository(Airport::class)->findOneBy(['iataCode' => $code]) instanceof Airport);

        return $code;
    }
}
