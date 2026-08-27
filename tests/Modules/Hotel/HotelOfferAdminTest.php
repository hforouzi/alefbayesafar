<?php

namespace App\Tests\Modules\Hotel;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Entity\State;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelOffer;
use App\Modules\Hotel\Entity\HotelSourceReference;
use App\Modules\Hotel\ValueObject\HotelOfferCandidate;
use App\Modules\Hotel\ValueObject\HotelOfferSearchRequest;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\SearchSource\Provider\FirecrawlClient;
use App\Modules\User\Entity\Role;
use App\Modules\User\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Routing\RouterInterface;

class HotelOfferAdminTest extends WebTestCase
{
    public function testHotelOfferRouteExistsAndRequiresLogin(): void
    {
        $client = self::createClient();
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);
        self::assertNotNull($router->getRouteCollection()->get('hotel_offer_search'));

        $client->request('GET', '/admin/catalog/hotels/1/offers');

        self::assertResponseRedirects('/login');
    }

    public function testAdminCanSearchDisplayAndStoreHotelOffers(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());
        $this->ensureHotelOfferSchema();
        $suffix = self::uniqueSuffix();
        $em = $this->entityManager();
        $this->disableExistingSearchSources();
        $city = $this->persistCity($suffix);
        $hotel = $this->persistHotel($city, $suffix);
        $booking = $this->persistSource('Booking ' . $suffix, 'booking-' . strtolower($suffix) . '.example.test', 1, $city->getCountry());
        $agoda = $this->persistSource('Agoda ' . $suffix, 'agoda-' . strtolower($suffix) . '.example.test', 2, $city->getCountry());
        $hotels = $this->persistSource('Hotels ' . $suffix, 'hotels-' . strtolower($suffix) . '.example.test', 3, $city->getCountry());
        $this->persistSourceReference($hotel, $booking);
        $this->persistSourceReference($hotel, $agoda);
        $this->persistSourceReference($hotel, $hotels);
        $request = $this->offerRequest();
        $this->persistOffer($hotel, $agoda, $request, 'agoda-old-' . $suffix, '444.00');
        $this->persistOffer($hotel, $hotels, $request, 'hotels-old-' . $suffix, '333.00');

        self::getContainer()->set(FirecrawlClient::class, new FirecrawlClient(new MockHttpClient([
            new MockResponse(json_encode([
                'success' => true,
                'data' => [
                    'json' => [
                        'offers' => [
                            [
                                'externalOfferId' => 'booking-offer-1-' . $suffix,
                                'roomName' => 'Deluxe Double Room',
                                'boardType' => 'Breakfast included',
                                'currency' => 'eur',
                                'totalPrice' => '620.00',
                                'bookingUrl' => 'https://' . $booking->getDomain() . '/book/1',
                                'availabilityStatus' => 'available',
                                'checkIn' => '2026-09-10',
                                'checkOut' => '2026-09-15',
                                'adults' => 2,
                                'children' => 1,
                                'childrenAges' => [4],
                            ],
                            [
                                'externalOfferId' => null,
                                'roomName' => 'Standard Room',
                                'boardType' => null,
                                'currency' => 'EUR',
                                'totalPrice' => '590',
                                'bookingUrl' => null,
                                'availabilityStatus' => null,
                                'checkIn' => '2026-09-10',
                                'checkOut' => '2026-09-15',
                                'adults' => 2,
                                'children' => 1,
                                'childrenAges' => [4],
                            ],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
            new MockResponse('{"success":false,"error":"timeout"}', ['http_code' => 500]),
            new MockResponse(json_encode([
                'success' => true,
                'data' => ['json' => ['offers' => []]],
            ], JSON_THROW_ON_ERROR)),
        ]), 'key', 'https://firecrawl.test'));

        $crawler = $client->request('GET', sprintf('/admin/catalog/hotels/%d/offers', $hotel->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'External Pricing Test');
        self::assertSelectorExists('form[name="hotel_offer_search"]');
        self::assertSelectorExists('[data-controller="locale-date"]');
        self::assertSelectorExists('[data-controller="child-ages"]');
        self::assertSelectorExists('input[name="hotel_offer_search[checkIn]"]');
        self::assertSelectorExists('input[name="hotel_offer_search[checkOut]"]');
        self::assertSelectorExists('input[name="hotel_offer_search[adults]"]');
        self::assertSelectorExists('input[name="hotel_offer_search[children]"]');
        self::assertSelectorExists('input[name="hotel_offer_search[childrenAges]"]');
        self::assertSelectorExists('input[data-child-ages-target="count"]');
        self::assertSelectorExists('input[data-child-ages-target="field"]');

        $client->submit($crawler->filter('form[name="hotel_offer_search"]')->form([
            'hotel_offer_search[checkIn]' => '2026-09-10',
            'hotel_offer_search[checkOut]' => '2026-09-15',
            'hotel_offer_search[adults]' => '2',
            'hotel_offer_search[children]' => '1',
            'hotel_offer_search[childrenAges]' => '4',
        ]));

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertSelectorExists('#hotel-offer-search-result');
        self::assertSelectorTextContains('#hotel-offer-search-result', 'SEARCH COMPLETED');
        self::assertSelectorExists('[data-dev-diagnostics-panel]');
        self::assertSelectorNotExists('details');
        self::assertSelectorTextContains('[data-dev-diagnostics-panel]', 'SEARCH EXECUTED');
        self::assertSelectorTextContains('[data-dev-diagnostics-panel]', 'Form valid: YES');
        self::assertSelectorTextContains('[data-dev-diagnostics-panel]', 'Search service called: YES');
        self::assertSelectorTextContains('[data-dev-diagnostics-panel]', 'Provider called: YES');
        self::assertSelectorTextContains('[data-dev-diagnostics-panel]', 'Raw offers: 2');
        self::assertSelectorTextContains('[data-dev-diagnostics-panel]', 'Accepted offers: 2');
        self::assertSelectorTextContains('[data-dev-diagnostics-panel]', 'structuredExtraction JSON');
        self::assertGreaterThan(0, strpos($html, 'id="hotel-offer-search-result"'));
        self::assertGreaterThan(
            strpos($html, 'id="hotel-offer-search-result"'),
            strpos($html, '2 offer(s) stored.'),
            'Search result must render before the stored-offer section.'
        );
        self::assertSelectorTextContains('body', 'Booking ' . $suffix);
        self::assertSelectorTextContains('body', 'Agoda ' . $suffix);
        self::assertSelectorTextContains('body', 'Hotels ' . $suffix);
        self::assertSelectorTextContains('body', 'Deluxe Double Room');
        self::assertSelectorTextContains('body', 'Breakfast included');
        self::assertSelectorTextContains('body', '620.00 EUR');
        self::assertSelectorTextContains('body', '590.00 EUR');
        self::assertSelectorTextContains('body', 'Offer lookup failed');
        self::assertSelectorTextContains('body', 'No reliable offer found for this search.');
        self::assertSelectorTextContains('body', 'Developer Diagnostics');
        self::assertSelectorTextContains('body', 'submitted');
        self::assertSelectorTextContains('body', 'valid');
        self::assertSelectorTextContains('body', 'searchServiceCalled');
        self::assertSelectorTextContains('body', 'storeServiceCalled');
        self::assertSelectorTextContains('body', 'Search context');
        self::assertSelectorTextContains('body', 'source ID');
        self::assertSelectorTextContains('body', 'Structured extraction');
        self::assertSelectorTextContains('body', 'Offer diagnostics');
        self::assertSelectorTextContains('body', 'source_reference');
        self::assertSelectorTextContains('body', 'contextUrlUsed');
        self::assertSelectorTextContains('body', '620.00');
        self::assertSelectorTextNotContains('body', 'Bearer key');
        self::assertSelectorExists('a[href="https://' . $booking->getDomain() . '/book/1"]');
        self::assertSelectorNotExists('a[href$="/book/2"]');

        $em->clear();
        $stored = $em->getRepository(HotelOffer::class)->findBy(['hotel' => $hotel], ['totalPrice' => 'ASC']);
        self::assertCount(4, $stored);
        self::assertNotNull($em->getRepository(HotelOffer::class)->findOneBy(['externalOfferId' => 'agoda-old-' . $suffix]));
        self::assertNotNull($em->getRepository(HotelOffer::class)->findOneBy(['externalOfferId' => 'hotels-old-' . $suffix]));
        $bookingOffers = array_values(array_filter($stored, static fn (HotelOffer $offer): bool => $offer->getSearchSource()?->getId() === $booking->getId()));
        self::assertCount(2, $bookingOffers);
        self::assertSame('firecrawl', $bookingOffers[0]->getProviderCode());
        self::assertEquals(new \DateTimeImmutable('2026-09-10'), $bookingOffers[0]->getCheckIn());
        self::assertSame(2, $bookingOffers[0]->getAdults());
        self::assertSame(1, $bookingOffers[0]->getChildren());
        self::assertSame([4], $bookingOffers[0]->getChildrenAges());
    }

    public function testInvalidOfferSearchFormIsRejected(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());
        $this->ensureHotelOfferSchema();
        $hotel = $this->persistHotel($this->persistCity(self::uniqueSuffix()), self::uniqueSuffix());

        $crawler = $client->request('GET', sprintf('/admin/catalog/hotels/%d/offers', $hotel->getId()));
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('form[name="hotel_offer_search"]')->form([
            'hotel_offer_search[checkIn]' => '2026-09-10',
            'hotel_offer_search[checkOut]' => '2026-09-10',
            'hotel_offer_search[adults]' => '0',
            'hotel_offer_search[children]' => '-1',
            'hotel_offer_search[childrenAges]' => 'abc',
        ]));

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->entityManager()->getRepository(HotelOffer::class)->findBy(['hotel' => $hotel]));

        $crawler = $client->request('GET', sprintf('/admin/catalog/hotels/%d/offers', $hotel->getId()));
        $client->submit($crawler->filter('form[name="hotel_offer_search"]')->form([
            'hotel_offer_search[checkIn]' => '2026-09-10',
            'hotel_offer_search[checkOut]' => '2026-09-10',
            'hotel_offer_search[adults]' => '1',
            'hotel_offer_search[children]' => '0',
            'hotel_offer_search[childrenAges]' => '',
        ]));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Check-out must be after check-in.');
        self::assertSame([], $this->entityManager()->getRepository(HotelOffer::class)->findBy(['hotel' => $hotel]));

        $crawler = $client->request('GET', sprintf('/admin/catalog/hotels/%d/offers', $hotel->getId()));
        $client->submit($crawler->filter('form[name="hotel_offer_search"]')->form([
            'hotel_offer_search[checkIn]' => '2026-09-10',
            'hotel_offer_search[checkOut]' => '2026-09-15',
            'hotel_offer_search[adults]' => '1',
            'hotel_offer_search[children]' => '1',
            'hotel_offer_search[childrenAges]' => '',
        ]));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Please enter one age for each child.');

        $crawler = $client->request('GET', sprintf('/admin/catalog/hotels/%d/offers', $hotel->getId()));
        $client->submit($crawler->filter('form[name="hotel_offer_search"]')->form([
            'hotel_offer_search[checkIn]' => '2026-09-10',
            'hotel_offer_search[checkOut]' => '2026-09-15',
            'hotel_offer_search[adults]' => '1',
            'hotel_offer_search[children]' => '1',
            'hotel_offer_search[childrenAges]' => 'four',
        ]));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Child ages must be valid numbers.');

        $crawler = $client->request('GET', sprintf('/admin/catalog/hotels/%d/offers', $hotel->getId()));
        $client->submit($crawler->filter('form[name="hotel_offer_search"]')->form([
            'hotel_offer_search[checkIn]' => '2026-09-10',
            'hotel_offer_search[checkOut]' => '2026-09-15',
            'hotel_offer_search[adults]' => '1',
            'hotel_offer_search[children]' => '0',
            'hotel_offer_search[childrenAges]' => '4',
        ]));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Please enter one age for each child.');

        $crawler = $client->request('GET', sprintf('/admin/catalog/hotels/%d/offers', $hotel->getId()));
        $client->submit($crawler->filter('form[name="hotel_offer_search"]')->form([
            'hotel_offer_search[checkIn]' => '1405/06/19',
            'hotel_offer_search[checkOut]' => '1405/06/24',
            'hotel_offer_search[adults]' => '2',
            'hotel_offer_search[children]' => '0',
            'hotel_offer_search[childrenAges]' => '',
        ]));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Search could not run. Fix the form errors below.');
        self::assertSelectorTextContains('body', 'Search was not run because the submitted form has validation errors.');
        self::assertSelectorTextContains('body', 'Enter a valid date.');
        self::assertSelectorTextContains('body', 'Developer Diagnostics');
        self::assertSelectorTextContains('body', 'rawSubmittedData');
        self::assertSelectorTextContains('body', '1405\\/06\\/19');
        self::assertSelectorTextContains('body', 'searchServiceCalled');
        self::assertSelectorTextContains('body', 'false');
        self::assertSelectorTextNotContains('body', 'Offer lookup failed');
        self::assertSame([], $this->entityManager()->getRepository(HotelOffer::class)->findBy(['hotel' => $hotel]));

    }

    public function testAllEmptyOrFailedSourcesRenderWithoutDeletingFailedSnapshots(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());
        $this->ensureHotelOfferSchema();
        $suffix = self::uniqueSuffix();
        $this->disableExistingSearchSources();
        $city = $this->persistCity($suffix);
        $hotel = $this->persistHotel($city, $suffix);
        $failed = $this->persistSource('Failed ' . $suffix, 'failed-' . strtolower($suffix) . '.example.test', 1, $city->getCountry());
        $empty = $this->persistSource('Empty ' . $suffix, 'empty-' . strtolower($suffix) . '.example.test', 2, $city->getCountry());
        $this->persistSourceReference($hotel, $failed);
        $this->persistSourceReference($hotel, $empty);
        $this->persistOffer($hotel, $failed, $this->offerRequest(), 'failed-old-' . $suffix, '700.00');
        $this->persistOffer($hotel, $empty, $this->offerRequest(), 'empty-old-' . $suffix, '710.00');

        self::getContainer()->set(FirecrawlClient::class, new FirecrawlClient(new MockHttpClient([
            new MockResponse('{"success":false,"error":"server unavailable"}', ['http_code' => 500]),
            new MockResponse(json_encode(['success' => true, 'data' => ['json' => ['offers' => []]]], JSON_THROW_ON_ERROR)),
        ]), 'key', 'https://firecrawl.test'));

        $crawler = $client->request('GET', sprintf('/admin/catalog/hotels/%d/offers', $hotel->getId()));
        $client->submit($crawler->filter('form[name="hotel_offer_search"]')->form([
            'hotel_offer_search[checkIn]' => '2026-09-10',
            'hotel_offer_search[checkOut]' => '2026-09-15',
            'hotel_offer_search[adults]' => '2',
            'hotel_offer_search[children]' => '1',
            'hotel_offer_search[childrenAges]' => '4',
        ]));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Offer lookup failed');
        self::assertSelectorTextContains('body', 'No reliable offer found for this search.');
        self::assertSelectorExists('[data-dev-diagnostics-panel]');
        self::assertSelectorNotExists('details');
        self::assertSelectorTextContains('[data-dev-diagnostics-panel]', 'SEARCH EXECUTED');
        self::assertSelectorTextContains('[data-dev-diagnostics-panel]', 'Firecrawl returned 0 raw offers.');
        self::assertSelectorTextContains('body', 'Search completed successfully.');
        self::assertSelectorTextContains('body', 'No extracted offer contained reliable price and availability data.');
        self::assertNotNull($this->entityManager()->getRepository(HotelOffer::class)->findOneBy(['externalOfferId' => 'failed-old-' . $suffix]));
        self::assertNotNull($this->entityManager()->getRepository(HotelOffer::class)->findOneBy(['externalOfferId' => 'empty-old-' . $suffix]));
    }

    private function createSuperAdminUser(): UserEntity
    {
        $em = $this->entityManager();
        $role = $em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);
        if (!$role instanceof Role) {
            $role = (new Role())->setName('ROLE_SUPER_ADMIN');
            $em->persist($role);
        }

        $user = (new UserEntity())
            ->setEmail('phase6-offer-admin-' . strtolower(self::uniqueSuffix()) . '@example.test')
            ->setPassword('not-used')
            ->addUserRole($role);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function persistCity(string $suffix): City
    {
        $country = (new Country())->setName('Offer Country ' . $suffix);
        $state = (new State())
            ->setCountry($country)
            ->setName('Offer State ' . $suffix)
            ->setCode('O' . substr($suffix, 0, 2))
            ->setSlug('offer-state-' . strtolower($suffix));
        $city = (new City())
            ->setCountry($country)
            ->setState($state)
            ->setName('Offer City ' . $suffix)
            ->setSlug('offer-city-' . strtolower($suffix));

        $em = $this->entityManager();
        foreach ([$country, $state, $city] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        return $city;
    }

    private function persistHotel(City $city, string $suffix): Hotel
    {
        $hotel = (new Hotel())
            ->setCity($city)
            ->setName('Fixture Stay Hotel ' . $suffix)
            ->setSlug('fixture-stay-hotel-' . strtolower($suffix));

        $this->entityManager()->persist($hotel);
        $this->entityManager()->flush();

        return $hotel;
    }

    private function persistSource(string $name, string $domain, int $priority, ?Country $country): SearchSource
    {
        $source = (new SearchSource())
            ->setName($name)
            ->setDomain($domain)
            ->setProvider('firecrawl')
            ->setProviderType(SearchSourceProviderType::FIRECRAWL)
            ->setCapabilities([SearchSource::CAPABILITY_HOTEL])
            ->setPriority($priority)
            ->setCountry($country)
            ->setEnabled(true);

        $this->entityManager()->persist($source);
        $this->entityManager()->flush();

        return $source;
    }

    private function disableExistingSearchSources(): void
    {
        $em = $this->entityManager();
        foreach ($em->getRepository(SearchSource::class)->findAll() as $source) {
            $source->setEnabled(false);
        }
        $em->flush();
    }

    private function persistSourceReference(Hotel $hotel, SearchSource $source): void
    {
        $reference = (new HotelSourceReference())
            ->setHotel($hotel)
            ->setSource(HotelOfferCandidate::sourceIdentifier($source))
            ->setExternalId('hotel-' . strtolower(self::uniqueSuffix()))
            ->setSourceUrl('https://' . $source->getDomain() . '/hotel/' . $hotel->getSlug())
            ->setSourceTitle($hotel->getName());

        $hotel->addSourceReference($reference);
        $this->entityManager()->persist($reference);
        $this->entityManager()->flush();
    }

    private function persistOffer(Hotel $hotel, SearchSource $source, HotelOfferSearchRequest $request, string $externalId, string $price): HotelOffer
    {
        $offer = (new HotelOffer())
            ->setHotel($hotel)
            ->setSearchSource($source)
            ->setProviderCode('firecrawl')
            ->setExternalOfferId($externalId)
            ->setCheckIn($request->checkIn)
            ->setCheckOut($request->checkOut)
            ->setAdults($request->adults)
            ->setChildren($request->children)
            ->setChildrenAges($request->childrenAges)
            ->setCurrency('EUR')
            ->setTotalPrice($price)
            ->setFetchedAt(new \DateTimeImmutable('2026-08-01 12:00:00'));

        $this->entityManager()->persist($offer);
        $this->entityManager()->flush();

        return $offer;
    }

    private function offerRequest(): HotelOfferSearchRequest
    {
        return new HotelOfferSearchRequest(new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), 2, 1, [4]);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function ensureHotelOfferSchema(): void
    {
        $connection = $this->entityManager()->getConnection();
        if ($connection->createSchemaManager()->tablesExist(['hotel_offer'])) {
            if (!$connection->createSchemaManager()->introspectTable('hotel_offer')->hasColumn('children_ages')) {
                $connection->executeStatement('ALTER TABLE hotel_offer ADD children_ages JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
                $connection->executeStatement('UPDATE hotel_offer SET children_ages = \'[]\' WHERE children_ages IS NULL');
                $connection->executeStatement('ALTER TABLE hotel_offer CHANGE children_ages children_ages JSON NOT NULL COMMENT \'(DC2Type:json)\'');
            }

            return;
        }

        $connection->executeStatement('CREATE TABLE hotel_offer (id INT AUTO_INCREMENT NOT NULL, hotel_id INT NOT NULL, search_source_id INT NOT NULL, provider_code VARCHAR(64) NOT NULL, external_offer_id VARCHAR(190) DEFAULT NULL, check_in DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', check_out DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', adults SMALLINT NOT NULL, children SMALLINT NOT NULL, children_ages JSON NOT NULL COMMENT \'(DC2Type:json)\', room_name VARCHAR(255) DEFAULT NULL, board_type VARCHAR(120) DEFAULT NULL, currency VARCHAR(3) NOT NULL, total_price NUMERIC(12, 2) NOT NULL, booking_url VARCHAR(2048) DEFAULT NULL, availability_status VARCHAR(32) DEFAULT NULL, fetched_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', metadata JSON NOT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_hotel_offer_hotel (hotel_id), INDEX idx_hotel_offer_search_source (search_source_id), INDEX idx_hotel_offer_fetched_at (fetched_at), INDEX idx_hotel_offer_stay_travelers (hotel_id, check_in, check_out, adults, children), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $connection->executeStatement('ALTER TABLE hotel_offer ADD CONSTRAINT FK_CA0249FA3243BB18 FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
        $connection->executeStatement('ALTER TABLE hotel_offer ADD CONSTRAINT FK_CA0249FA2E270CC9 FOREIGN KEY (search_source_id) REFERENCES search_source (id) ON DELETE RESTRICT');
    }

    private static function uniqueSuffix(): string
    {
        $letters = '';
        for ($index = 0; $index < 6; $index++) {
            $letters .= chr(random_int(65, 90));
        }

        return $letters;
    }
}
