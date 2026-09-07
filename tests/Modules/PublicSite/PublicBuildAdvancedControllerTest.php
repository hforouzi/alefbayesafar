<?php

namespace App\Tests\Modules\PublicSite;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\Tour\Entity\ExternalTourOffer;
use App\Modules\Tour\Enum\TourAvailabilityStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Covers the secondary "advanced search" structured form at /build/advanced.
 * The primary /build experience is chat-first — see
 * PublicBuildChatControllerTest and ConversationalTravelPlanningServiceTest.
 */
class PublicBuildAdvancedControllerTest extends WebTestCase
{
    public function testGetBuildAdvancedRendersLightweightSearchForm(): void
    {
        $client = self::createClient();
        $client->request('GET', '/build/advanced');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorNotExists('input[name*="originAirportId"]');
        self::assertSelectorTextContains('body', 'جستجوی پیشرفته');
    }

    public function testOriginCityWithoutAirportAndDestinationCountryOnlyIsAccepted(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        [$originCity, , $destinationCountry] = $this->catalog($em);
        $em->flush();

        $crawler = $client->request('GET', '/build/advanced');
        $form = $crawler->filter('form')->form([
            'public_trip_search[originCityId]' => (string) $originCity->getId(),
            'public_trip_search[destinationCityId]' => '',
            'public_trip_search[destinationCountryId]' => (string) $destinationCountry->getId(),
            'public_trip_search[dateMode]' => 'flexible',
            'public_trip_search[windowStart]' => '2026-09-23',
            'public_trip_search[windowEnd]' => '2026-10-22',
            'public_trip_search[nights]' => '5',
            'public_trip_search[adults]' => '2',
            'public_trip_search[children]' => '0',
            'public_trip_search[flightCabin]' => 'economy',
        ]);
        $client->submit($form);

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('leaving from', $html);
        self::assertStringNotContainsString('destination city or country', $html);
    }

    public function testDestinationCityFlexibleSearchRendersRecommendationWithHotelContextAndExplanation(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        [$originCity, $originAirport, , $destinationCity] = $this->catalog($em);
        $source = $this->tourSource();
        $offer = (new ExternalTourOffer())
            ->setSearchSource($source)
            ->setProviderCode('test')
            ->setTitle('Public Istanbul 5 Nights ' . self::suffix())
            ->setOriginAirport($originAirport)
            ->setOriginText((string) $originCity->getName())
            ->setDestinationCity($destinationCity)
            ->setDestinationText($destinationCity->getName())
            ->setDepartureDate(new \DateTimeImmutable('2026-10-01'))
            ->setReturnDate(new \DateTimeImmutable('2026-10-06'))
            ->setNights(5)
            ->setHotelName('Reviewed Public Hotel')
            ->setBoardType('breakfast')
            ->setAdults(2)
            ->setChildren(0)
            ->setInfants(0)
            ->setCurrency('EUR')
            ->setTotalPrice('690.00')
            ->setAvailabilityStatus(TourAvailabilityStatus::AVAILABLE)
            ->setFetchedAt(new \DateTimeImmutable('-1 hour'))
            ->setExpiresAt(new \DateTimeImmutable('+5 hours'))
            ->setMetadata([
                'hotelName' => 'Reviewed Public Hotel',
                'rawProviderOffer' => [
                    'hotels' => [[
                        'hotel' => [
                            'titleEn' => 'Reviewed Public Hotel',
                            'averageReviewScore' => 4.32,
                            'reviewsCount' => 36,
                        ],
                    ]],
                ],
            ]);
        $em->persist($source);
        $em->persist($offer);
        $em->flush();

        $crawler = $client->request('GET', '/build/advanced');
        $form = $crawler->filter('form')->form([
            'public_trip_search[originCityId]' => (string) $originCity->getId(),
            'public_trip_search[destinationCityId]' => (string) $destinationCity->getId(),
            'public_trip_search[destinationCountryId]' => (string) $destinationCity->getCountry()?->getId(),
            'public_trip_search[dateMode]' => 'flexible',
            'public_trip_search[windowStart]' => '2026-09-23',
            'public_trip_search[windowEnd]' => '2026-10-22',
            'public_trip_search[nights]' => '5',
            'public_trip_search[adults]' => '2',
            'public_trip_search[children]' => '0',
            'public_trip_search[flightCabin]' => 'economy',
        ]);
        $client->submit($form);

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Public Istanbul 5 Nights', $html);
        self::assertStringContainsString('Reviewed Public Hotel', $html);
        self::assertStringContainsString('4.32', $html);
        self::assertStringContainsString('36', $html);
        self::assertStringContainsString('690.00', $html);
        self::assertStringContainsString('EUR', $html);

        // Explanation layer must have produced factual text derived from the same data.
        self::assertStringContainsString('690.00', explode('trip-results', $html)[1] ?? '');
    }

    public function testExactDateModeIsAccepted(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        [$originCity, , , $destinationCity] = $this->catalog($em);
        $em->flush();

        $crawler = $client->request('GET', '/build/advanced');
        $form = $crawler->filter('form')->form([
            'public_trip_search[originCityId]' => (string) $originCity->getId(),
            'public_trip_search[destinationCityId]' => (string) $destinationCity->getId(),
            'public_trip_search[destinationCountryId]' => (string) $destinationCity->getCountry()?->getId(),
            'public_trip_search[dateMode]' => 'exact',
            'public_trip_search[departureDate]' => '2026-10-01',
            'public_trip_search[returnDate]' => '2026-10-06',
            'public_trip_search[adults]' => '2',
            'public_trip_search[children]' => '0',
            'public_trip_search[flightCabin]' => 'economy',
        ]);
        $client->submit($form);

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('choose the start of your travel window', $html);
        self::assertStringNotContainsString('choose a departure date', $html);
    }

    public function testNoOriginProducesUserFacingValidationMessage(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        [, , , $destinationCity] = $this->catalog($em);
        $em->flush();

        $crawler = $client->request('GET', '/build/advanced');
        $form = $crawler->filter('form')->form([
            'public_trip_search[originCityId]' => '',
            'public_trip_search[destinationCityId]' => (string) $destinationCity->getId(),
            'public_trip_search[destinationCountryId]' => (string) $destinationCity->getCountry()?->getId(),
            'public_trip_search[dateMode]' => 'flexible',
            'public_trip_search[windowStart]' => '2026-09-23',
            'public_trip_search[windowEnd]' => '2026-10-22',
            'public_trip_search[nights]' => '5',
            'public_trip_search[adults]' => '2',
            'public_trip_search[children]' => '0',
            'public_trip_search[flightCabin]' => 'economy',
        ]);
        $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'leaving from');
    }

    public function testNoResultsShowsHelpfulNonTechnicalMessage(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        [$originCity, , , $destinationCity] = $this->catalog($em);
        $em->flush();

        $crawler = $client->request('GET', '/build/advanced');
        $form = $crawler->filter('form')->form([
            'public_trip_search[originCityId]' => (string) $originCity->getId(),
            'public_trip_search[destinationCityId]' => (string) $destinationCity->getId(),
            'public_trip_search[destinationCountryId]' => (string) $destinationCity->getCountry()?->getId(),
            'public_trip_search[dateMode]' => 'flexible',
            'public_trip_search[windowStart]' => '2026-09-23',
            'public_trip_search[windowEnd]' => '2026-10-22',
            'public_trip_search[nights]' => '5',
            'public_trip_search[adults]' => '2',
            'public_trip_search[children]' => '0',
            'public_trip_search[flightCabin]' => 'economy',
        ]);
        $client->submit($form);

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('هنوز پیشنهادی پیدا نشد', $html);
        self::assertStringNotContainsString('no_options', $html);
    }

    public function testAdminDebugPageStillWorksAndIsSeparateFromPublicPage(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/trip-planner/test/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Trip Planner Test');
    }

    public function testPublicResultsDoNotLeakInternalProviderVocabulary(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        [$originCity, $originAirport, , $destinationCity] = $this->catalog($em);
        $source = $this->tourSource();
        $offer = (new ExternalTourOffer())
            ->setSearchSource($source)
            ->setProviderCode('test')
            ->setTitle('Vocabulary Check Tour ' . self::suffix())
            ->setOriginAirport($originAirport)
            ->setOriginText((string) $originCity->getName())
            ->setDestinationCity($destinationCity)
            ->setDestinationText($destinationCity->getName())
            ->setDepartureDate(new \DateTimeImmutable('2026-10-01'))
            ->setReturnDate(new \DateTimeImmutable('2026-10-06'))
            ->setNights(5)
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

        $crawler = $client->request('GET', '/build/advanced');
        $form = $crawler->filter('form')->form([
            'public_trip_search[originCityId]' => (string) $originCity->getId(),
            'public_trip_search[destinationCityId]' => (string) $destinationCity->getId(),
            'public_trip_search[destinationCountryId]' => (string) $destinationCity->getCountry()?->getId(),
            'public_trip_search[dateMode]' => 'flexible',
            'public_trip_search[windowStart]' => '2026-09-23',
            'public_trip_search[windowEnd]' => '2026-10-22',
            'public_trip_search[nights]' => '5',
            'public_trip_search[adults]' => '2',
            'public_trip_search[children]' => '0',
            'public_trip_search[flightCabin]' => 'economy',
        ]);
        $client->submit($form);

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        foreach ([
            'cached_external', 'provider_error', 'no_configured_sources', 'no_data', 'hotel_review_context_unavailable',
            'liveRefreshStatus', 'canonicalMatchStatus', 'rawResultCount', 'searchContextHash',
            'live_external', 'budget fit', 'lower-priced matching departure', 'matched option within requested date window',
            'external offer', 'no_options',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $html, \sprintf('Public page must not leak internal vocabulary "%s".', $forbidden));
        }
    }

    /**
     * @return array{0: City, 1: Airport, 2: Country, 3: City}
     */
    private function catalog(EntityManagerInterface $em): array
    {
        $suffix = self::suffix();
        $originCountry = (new Country())->setName('Public Origin Country ' . $suffix);
        $destinationCountry = (new Country())->setName('Public Destination Country ' . $suffix);
        $originCity = (new City())->setCountry($originCountry)->setName('Rasht ' . $suffix)->setSlug('public-rasht-' . strtolower($suffix));
        $destinationCity = (new City())->setCountry($destinationCountry)->setName('Istanbul ' . $suffix)->setSlug('public-istanbul-' . strtolower($suffix));
        $originAirport = (new Airport())->setCity($originCity)->setName('Public Origin Airport ' . $suffix)->setIataCode($this->uniqueIata($em));

        foreach ([$originCountry, $destinationCountry, $originCity, $destinationCity, $originAirport] as $entity) {
            $em->persist($entity);
        }

        return [$originCity, $originAirport, $destinationCountry, $destinationCity];
    }

    private function tourSource(): SearchSource
    {
        return (new SearchSource())
            ->setName('Public Build Tour Source ' . self::suffix())
            ->setDomain('example.test')
            ->setProvider('test')
            ->setProviderType(SearchSourceProviderType::MANUAL)
            ->setCapabilities([SearchSource::CAPABILITY_TOUR])
            ->setEnabled(true);
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
