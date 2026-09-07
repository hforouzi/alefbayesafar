<?php

namespace App\Tests\Modules\PublicSite;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Provider\DestinationInsightProviderInterface;
use App\Modules\Destination\ValueObject\DestinationInsight;
use App\Modules\Destination\ValueObject\DestinationInsightResult;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\Tour\Entity\ExternalTourOffer;
use App\Modules\Tour\Enum\TourAvailabilityStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * HTTP-level coverage for the chat-first /build experience. The interpreter
 * logic itself is covered by ConversationalTravelPlanningServiceTest; these
 * tests only confirm the controller/session/redirect wiring and that the
 * public page never shows a structured multi-field form or an airport
 * field as the primary interaction.
 */
class PublicBuildChatControllerTest extends WebTestCase
{
    public function testInitialBuildPageIsChatFirstNotFormFirst(): void
    {
        $client = self::createClient();
        $client->request('GET', '/build');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'برای سفرت چه چیزی تو ذهنت هست؟');
        self::assertSelectorExists('textarea[name="message"]');
        self::assertSelectorNotExists('input[name*="originCityId"]');
        self::assertSelectorNotExists('input[name*="originAirportId"]');
        self::assertSelectorNotExists('select[name*="hotelStarPreference"]');
    }

    public function testFirstMessageAsksForOriginAndNeverAsksForAirport(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $em->flush();

        $client->request('POST', '/build/message', ['message' => '۵ روز ' . $catalog['istanbulFa'] . ' می‌خوام']);
        self::assertResponseRedirects('/build');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('کدام شهر', $html);
        self::assertStringNotContainsString('airport', strtolower($html));
        self::assertStringNotContainsString('IATA', $html);
    }

    public function testConversationCompletesAndRendersCardsThenResetClearsIt(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $source = (new SearchSource())
            ->setName('Chat HTTP Source ' . self::suffix())
            ->setDomain('example.test')
            ->setProvider('test')
            ->setProviderType(SearchSourceProviderType::MANUAL)
            ->setCapabilities([SearchSource::CAPABILITY_TOUR])
            ->setEnabled(true);
        $offer = (new ExternalTourOffer())
            ->setSearchSource($source)
            ->setProviderCode('test')
            ->setTitle('Chat HTTP Offer ' . self::suffix())
            ->setDestinationCity($catalog['istanbul'])
            ->setDestinationText($catalog['istanbul']->getName())
            ->setDepartureDate(new \DateTimeImmutable('2026-10-01'))
            ->setReturnDate(new \DateTimeImmutable('2026-10-06'))
            ->setNights(5)
            ->setHotelName('Chat HTTP Hotel')
            ->setBoardType('breakfast')
            ->setAdults(2)
            ->setChildren(0)
            ->setInfants(0)
            ->setCurrency('EUR')
            ->setTotalPrice('690.00')
            ->setAvailabilityStatus(TourAvailabilityStatus::AVAILABLE)
            ->setFetchedAt(new \DateTimeImmutable('-1 hour'))
            ->setExpiresAt(new \DateTimeImmutable('+5 hours'))
            ->setMetadata([]);
        $em->persist($source);
        $em->persist($offer);
        $em->flush();

        $client->request('POST', '/build/message', ['message' => '۵ روز ' . $catalog['istanbulFa'] . ' می‌خوام']);
        $client->followRedirect();
        $client->request('POST', '/build/message', ['message' => $catalog['rashtFa']]);
        $client->followRedirect();
        $client->request('POST', '/build/message', ['message' => 'تو مهر هر وقت ارزون‌تره']);
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Chat HTTP Offer', $html);
        self::assertStringContainsString('Chat HTTP Hotel', $html);
        self::assertStringContainsString('690.00', $html);
        self::assertStringContainsString('صبحانه: دارد', $html, 'known breakfast board must render as a clear Persian fact line');
        self::assertStringContainsString('کدام گزینه بهتر است؟', $html, 'follow-up suggestion chips must be offered once results are shown');
        foreach ([
            'cached_external', 'provider_error', 'no_configured_sources', 'liveRefreshStatus', 'canonicalMatchStatus',
            'live_external', 'budget fit', 'lower-priced matching departure', 'matched option within requested date window',
            'external offer', 'no_options',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $html);
        }

        // ResultsWorkspace is emitted first in the DOM (per the required page structure) but
        // carries lg:order-2 so it renders visually on the LEFT under RTL; AdvisorPanel is
        // emitted second but carries lg:order-1 so it renders visually on the RIGHT.
        $resultsWorkspacePosition = strpos($html, 'order-1 min-w-0 space-y-6 lg:order-2');
        $advisorPanelPosition = strpos($html, 'order-2 flex min-w-0 flex-col lg:order-1');
        self::assertIsInt($resultsWorkspacePosition, 'the results workspace (lg:order-2, visually left) must be present');
        self::assertIsInt($advisorPanelPosition, 'the advisor panel (lg:order-1, visually right) must be present');
        self::assertLessThan($advisorPanelPosition, $resultsWorkspacePosition, 'ResultsWorkspace must be emitted before AdvisorPanel in the DOM');

        $resultsPosition = strpos($html, 'Chat HTTP Offer');
        self::assertIsInt($resultsPosition);
        self::assertGreaterThan($resultsWorkspacePosition, $resultsPosition, 'result cards must render inside the results workspace');
        self::assertLessThan($advisorPanelPosition, $resultsPosition, 'result cards must not render inside the advisor panel');

        $client->request('POST', '/build/reset');
        self::assertResponseRedirects('/build');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'برای سفرت چه چیزی تو ذهنت هست؟');
    }

    public function testShoppingPurposeRendersLovableStyleSectionsWithRealDestinationInsights(): void
    {
        $client = self::createClient();
        // The client reboots the kernel (and its container) before every request()
        // call by default, which would silently discard the service override below.
        $client->disableReboot();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $this->offer($em, $catalog['istanbul'], 'Shopping Trip Offer ' . self::suffix(), '690.00', 'Shopping Trip Hotel');
        $em->flush();

        self::getContainer()->set(DestinationInsightProviderInterface::class, new class implements DestinationInsightProviderInterface {
            public function getCode(): string
            {
                return 'fake';
            }

            public function search(string $cityName, ?string $countryName, string $category, int $limit): DestinationInsightResult
            {
                return DestinationInsightResult::success([
                    new DestinationInsight(title: 'Istinye Park', category: 'shopping', city: $cityName, source: 'Tripadvisor', rating: '4.5', reviewCount: 1234, sourceUrl: 'https://tripadvisor.example/istinye-park'),
                ]);
            }
        });

        $client->request('POST', '/build/message', ['message' => '۵ روز ' . $catalog['istanbulFa'] . ' برای خرید']);
        $client->followRedirect();
        $client->request('POST', '/build/message', ['message' => $catalog['rashtFa']]);
        $client->followRedirect();
        $client->request('POST', '/build/message', ['message' => 'تو مهر هر وقت ارزون‌تره']);
        $crawler = $client->followRedirect();

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString($catalog['rashtFa'], $html);
        self::assertStringContainsString($catalog['istanbulFa'], $html);
        self::assertStringContainsString('پیشنهادهای سفر / پکیج‌ها', $html);
        self::assertStringContainsString('برای خرید', $html, 'the destination insight section heading must reflect the shopping purpose');
        self::assertStringContainsString('برآورد فعلی پکیج', $html);
        self::assertStringContainsString('Istinye Park', $html);
        self::assertStringContainsString('4.5', $html);
        self::assertStringContainsString('Tripadvisor', $html);
        self::assertStringContainsString('مشاهده جزئیات', $html);

        // Ask the shopping follow-up question and confirm it answers from the same real insights.
        $client->request('POST', '/build/message', ['message' => 'کجا برای خرید برم؟']);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        $followUpHtml = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Istinye Park', $followUpHtml);
    }

    public function testFollowUpQuestionAfterResultsGetsContextualAnswerNotGenericSummary(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $catalog = $this->catalog($em);
        $this->offer($em, $catalog['istanbul'], 'Follow-up Cheap Offer ' . self::suffix(), '600.00', 'Follow-up Cheap Hotel');
        $this->offer($em, $catalog['istanbul'], 'Follow-up Pricey Offer ' . self::suffix(), '750.00', 'Follow-up Pricey Hotel');
        $em->flush();

        $client->request('POST', '/build/message', ['message' => '۵ روز ' . $catalog['istanbulFa'] . ' می‌خوام']);
        $client->followRedirect();
        $client->request('POST', '/build/message', ['message' => $catalog['rashtFa']]);
        $client->followRedirect();
        $client->request('POST', '/build/message', ['message' => 'تو مهر هر وقت ارزون‌تره']);
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $client->request('POST', '/build/message', ['message' => 'کدوم ارزون‌تره؟']);
        $crawler = $client->followRedirect();

        self::assertResponseIsSuccessful();
        $bubbles = $crawler->filter('.rounded-2xl');
        self::assertGreaterThan(0, $bubbles->count());
        $lastMessage = trim($bubbles->last()->text());

        self::assertStringContainsString('ارزان‌ترین گزینه', $lastMessage);
        self::assertStringNotContainsString('گزینه مناسب پیدا کردم', $lastMessage, 'a follow-up answer must not repeat the original generic result summary');
    }

    public function testAdvancedSearchLinkIsAvailableAsSecondaryOption(): void
    {
        $client = self::createClient();
        $client->request('GET', '/build');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/build/advanced"]');
    }

    /**
     * @return array{rasht: City, istanbul: City, rashtFa: string, istanbulFa: string}
     */
    private function catalog(EntityManagerInterface $em): array
    {
        $suffix = self::suffix();
        $iranCountry = (new Country())->setName('Chat HTTP Iran ' . $suffix);
        $turkeyCountry = (new Country())->setName('Chat HTTP Turkey ' . $suffix)->setNameFa('ترکیه' . $suffix);
        $rashtFa = 'رشت' . $suffix;
        $rasht = (new City())->setCountry($iranCountry)->setName('Chat HTTP Rasht ' . $suffix)->setNameFa($rashtFa)->setSlug('chat-http-rasht-' . strtolower($suffix));
        $istanbulFa = 'استانبول' . $suffix;
        $istanbul = (new City())->setCountry($turkeyCountry)->setName('Chat HTTP Istanbul ' . $suffix)->setNameFa($istanbulFa)->setSlug('chat-http-istanbul-' . strtolower($suffix));

        foreach ([$iranCountry, $turkeyCountry, $rasht, $istanbul] as $entity) {
            $em->persist($entity);
        }

        return ['rasht' => $rasht, 'istanbul' => $istanbul, 'rashtFa' => $rashtFa, 'istanbulFa' => $istanbulFa];
    }

    private function offer(EntityManagerInterface $em, City $destination, string $title, string $price, string $hotelName): void
    {
        $source = (new SearchSource())
            ->setName('Chat HTTP Source ' . self::suffix())
            ->setDomain('example.test')
            ->setProvider('test')
            ->setProviderType(SearchSourceProviderType::MANUAL)
            ->setCapabilities([SearchSource::CAPABILITY_TOUR])
            ->setEnabled(true);
        $offer = (new ExternalTourOffer())
            ->setSearchSource($source)
            ->setProviderCode('test')
            ->setTitle($title)
            ->setDestinationCity($destination)
            ->setDestinationText($destination->getName())
            ->setDepartureDate(new \DateTimeImmutable('2026-10-01'))
            ->setReturnDate(new \DateTimeImmutable('2026-10-06'))
            ->setNights(5)
            ->setHotelName($hotelName)
            ->setBoardType('breakfast')
            ->setAdults(2)
            ->setChildren(0)
            ->setInfants(0)
            ->setCurrency('EUR')
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
}
