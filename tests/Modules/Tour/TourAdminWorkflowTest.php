<?php

namespace App\Tests\Modules\Tour;

use App\Modules\Default\Entity\Menu;
use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Flight\Entity\Airline;
use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Entity\FlightOfferLeg;
use App\Modules\Flight\Enum\FlightDirection;
use App\Modules\Flight\Enum\FlightPriceSourceType;
use App\Modules\Flight\Enum\FlightPricingMode;
use App\Modules\Flight\Enum\FlightTripType;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelRoomType;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\Tour\Entity\TourPackage;
use App\Modules\Tour\Entity\TourPackageImage;
use App\Modules\Tour\Repository\TourPackageRepository;
use App\Modules\User\Entity\Permission;
use App\Modules\User\Entity\Role;
use App\Modules\User\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\RouterInterface;

class TourAdminWorkflowTest extends WebTestCase
{
    public function testTourCommerceRoutesExistAndAnonymousUserRedirects(): void
    {
        $client = self::createClient();
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);

        foreach ([
            'tour_package_index',
            'tour_package_new',
            'tour_package_edit',
            'tour_package_toggle',
            'tour_package_image_new',
            'tour_package_image_edit',
            'tour_package_image_delete',
            'tour_lookup_hotels',
            'tour_lookup_room_types',
            'tour_lookup_own_flight_offers',
            'tour_external_test',
        ] as $routeName) {
            self::assertNotNull($router->getRouteCollection()->get($routeName), $routeName);
        }

        $client->request('GET', '/admin/tour-commerce/packages/');

        self::assertResponseRedirects('/login');
    }

    public function testAdminCanCreateEditToggleAndManageImages(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());

        $this->runBootstrap();

        $data = $this->catalogData();
        $ownFlight = $this->ownFlightOffer($data['cgn'], $data['ist'], $data['airline']);
        $source = (new SearchSource())
            ->setName('Tour External Flight ' . self::uniqueSuffix())
            ->setDomain('tour-external-' . strtolower(self::uniqueSuffix()) . '.example.test')
            ->setProvider('firecrawl')
            ->setProviderType(SearchSourceProviderType::FIRECRAWL)
            ->setCapabilities([SearchSource::CAPABILITY_FLIGHT])
            ->setConfig(['flightSearchUrlTemplate' => 'https://example.test/flights']);
        $externalFlight = (new FlightOffer())
            ->setSourceType(FlightPriceSourceType::EXTERNAL)
            ->setSearchSource($source)
            ->setProviderCode('firecrawl')
            ->setPricingMode(FlightPricingMode::TOTAL_PARTY)
            ->setTotalPrice('690.00')
            ->setFetchedAt(new \DateTimeImmutable('-1 minute'))
            ->setExpiresAt(new \DateTimeImmutable('+20 minutes'));
        $this->em()->persist($source);
        $this->em()->persist($externalFlight);
        $this->em()->flush();

        $client->request('GET', '/admin/tour-commerce/lookup/own-flight-offers', ['q' => 'TK1672']);
        self::assertResponseIsSuccessful();
        $payload = (string) $client->getResponse()->getContent();
        $resultIds = array_map(static fn (array $row): int => (int) $row['id'], json_decode($payload, true, 512, JSON_THROW_ON_ERROR)['results']);
        self::assertContains($ownFlight->getId(), $resultIds);
        self::assertNotContains($externalFlight->getId(), $resultIds);

        $crawler = $client->request('GET', '/admin/tour-commerce/packages/new');
        self::assertResponseIsSuccessful();
        $slug = 'istanbul-5-nights-' . strtolower(self::uniqueSuffix());
        $client->request('POST', '/admin/tour-commerce/packages/new', [
            'tour_package' => [
                '_token' => $this->formToken($crawler, 'tour_package'),
                'name' => 'Istanbul 5 Nights',
                'nameFa' => '',
                'slug' => $slug,
                'originAirportId' => (string) $data['cgn']->getId(),
                'destinationCityId' => (string) $data['istanbul']->getId(),
                'flightOfferId' => (string) $ownFlight->getId(),
                'hotelId' => (string) $data['hotel']->getId(),
                'hotelRoomTypeId' => (string) $data['room']->getId(),
                'departureDate' => '2026-09-10',
                'returnDate' => '2026-09-15',
                'validFrom' => '',
                'validTo' => '',
                'nights' => '5',
                'days' => '6',
                'adults' => '2',
                'children' => '1',
                'infants' => '0',
                'childrenAgesText' => '8',
                'pricingMode' => 'total_party',
                'currency' => 'EUR',
                'totalPrice' => '1490',
                'adultPrice' => '',
                'childPrice' => '',
                'infantPrice' => '',
                'boardType' => 'breakfast',
                'inclusions' => ['flight', 'hotel', 'breakfast', 'airport_transfer'],
                'exclusions' => ['visa'],
                'shortDescription' => 'Istanbul package',
                'description' => '',
                'priority' => '100',
                'active' => '1',
                'featured' => '1',
                'publicVisible' => '1',
            ],
        ]);
        self::assertResponseRedirects('/admin/tour-commerce/packages/');

        $package = $this->em()->getRepository(TourPackage::class)->findOneBy(['slug' => $slug]);
        self::assertInstanceOf(TourPackage::class, $package);
        self::assertSame('1490.00', $package->getTotalPrice());
        self::assertSame($ownFlight->getId(), $package->getFlightOffer()?->getId());
        self::assertSame($data['hotel']->getId(), $package->getHotel()?->getId());
        self::assertSame($data['room']->getId(), $package->getHotelRoomType()?->getId());

        $crawler = $client->request('GET', '/admin/tour-commerce/packages/' . $package->getId() . '/edit');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter(sprintf('a[href="/admin/tour-commerce/packages/%d/images/new"]', $package->getId()))->count());
        $client->request('POST', '/admin/tour-commerce/packages/' . $package->getId() . '/edit', [
            'tour_package' => [
                '_token' => $this->formToken($crawler, 'tour_package'),
                'name' => 'Istanbul 5 Nights',
                'nameFa' => '',
                'slug' => $slug,
                'originAirportId' => (string) $data['cgn']->getId(),
                'destinationCityId' => (string) $data['istanbul']->getId(),
                'flightOfferId' => (string) $ownFlight->getId(),
                'hotelId' => (string) $data['hotel']->getId(),
                'hotelRoomTypeId' => (string) $data['room']->getId(),
                'departureDate' => '2026-09-10',
                'returnDate' => '2026-09-15',
                'validFrom' => '',
                'validTo' => '',
                'nights' => '5',
                'days' => '6',
                'adults' => '2',
                'children' => '1',
                'infants' => '0',
                'childrenAgesText' => '8',
                'pricingMode' => 'per_passenger_type',
                'currency' => 'EUR',
                'totalPrice' => '',
                'adultPrice' => '600',
                'childPrice' => '400',
                'infantPrice' => '',
                'boardType' => 'breakfast',
                'inclusions' => ['flight', 'hotel', 'breakfast', 'airport_transfer'],
                'exclusions' => ['visa'],
                'shortDescription' => 'Istanbul package',
                'description' => '',
                'priority' => '90',
                'active' => '1',
                'featured' => '1',
                'publicVisible' => '1',
            ],
        ]);
        self::assertResponseRedirects('/admin/tour-commerce/packages/');

        $this->em()->clear();
        $updated = $this->em()->getRepository(TourPackage::class)->findOneBy(['slug' => $slug]);
        self::assertInstanceOf(TourPackage::class, $updated);
        self::assertSame('1600.00', $updated->calculatedPartyPrice());

        $crawler = $client->request('GET', '/admin/tour-commerce/packages/' . $updated->getId() . '/images/new');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/admin/tour-commerce/packages/' . $updated->getId() . '/images/new', [
            'tour_package_image' => [
                '_token' => $this->formToken($crawler, 'tour_package_image'),
                'path' => 'https://example.test/istanbul.jpg',
                'alt' => 'Istanbul',
                'altFa' => '',
                'position' => '0',
                'primary' => '1',
            ],
        ]);
        self::assertResponseRedirects('/admin/tour-commerce/packages/' . $updated->getId() . '/edit');
        self::assertSame(1, $this->em()->getRepository(TourPackageImage::class)->count(['tourPackage' => $updated]));

        $crawler = $client->request('GET', '/admin/tour-commerce/packages/', ['q' => $slug]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Istanbul 5 Nights');
        self::assertSelectorTextContains('body', 'Arts Hotel Istanbul Harbiye');
        self::assertSelectorTextContains('body', 'Adult 600.00 EUR / Child 400.00 EUR');

        $client->request('POST', '/admin/tour-commerce/packages/' . $updated->getId() . '/toggle', [
            '_token' => $crawler
                ->filter(sprintf('form[action="/admin/tour-commerce/packages/%d/toggle"] input[name="_token"]', $updated->getId()))
                ->attr('value'),
        ]);
        self::assertResponseRedirects('/admin/tour-commerce/packages/');

        $this->em()->clear();
        $toggled = $this->em()->getRepository(TourPackage::class)->find($updated->getId());
        self::assertInstanceOf(TourPackage::class, $toggled);
        self::assertFalse($toggled->isActive());
    }

    public function testBootstrapCreatesTourCommerceMenuAndPermissionsIdempotently(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $beforeTourPackages = $this->em()->getRepository(TourPackage::class)->count([]);

        $first = $this->runBootstrap();
        $second = $this->runBootstrap();

        self::assertSame(0, $first->getStatusCode());
        self::assertSame(0, $second->getStatusCode());
        self::assertSame(1, $this->em()->getRepository(Menu::class)->count(['route' => 'tour_package_index']));
        self::assertSame(1, $this->em()->getRepository(Menu::class)->count(['route' => 'tour_external_test']));
        self::assertSame(1, $this->em()->getRepository(Permission::class)->count(['name' => 'tour.package.view']));
        self::assertSame(1, $this->em()->getRepository(Permission::class)->count(['name' => 'tour.external_test.view']));
        self::assertSame($beforeTourPackages, $this->em()->getRepository(TourPackage::class)->count([]));
    }

    public function testExternalTourTestAdminShowsNoSourceState(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());

        $this->runBootstrap();
        foreach ($this->em()->getRepository(SearchSource::class)->findAll() as $source) {
            if ($source instanceof SearchSource && $source->supports(SearchSource::CAPABILITY_TOUR)) {
                $source->setEnabled(false);
            }
        }
        $this->em()->flush();
        $data = $this->catalogData();

        $crawler = $client->request('GET', '/admin/tour-commerce/external-test/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'هیچ منبع تور خارجی فعالی تنظیم نشده است.');

        $client->request('POST', '/admin/tour-commerce/external-test/', [
            'external_tour_test' => [
                '_token' => $this->formToken($crawler, 'external_tour_test'),
                'originAirportId' => (string) $data['cgn']->getId(),
                'destinationCityId' => (string) $data['istanbul']->getId(),
                'departureDate' => '',
                'returnDate' => '',
                'validFrom' => '',
                'validTo' => '',
                'nights' => '5',
                'rooms' => '1',
                'adults' => '2',
                'children' => '0',
                'childrenAgesText' => '',
                'infants' => '0',
                'budget' => '',
                'currency' => '',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'No sources configured');
        self::assertSelectorTextContains('body', 'No enabled external tour sources are configured.');
        self::assertSelectorTextContains('body', 'Stored matching offers');
    }

    public function testRepositoryReturnsOnlyActivePublicVisiblePackagesByFeaturedAndPriority(): void
    {
        self::createClient();
        $city = $this->catalogData()['istanbul'];
        $visibleLow = $this->package('visible-low-' . strtolower(self::uniqueSuffix()), $city, 10, false, true, true);
        $visibleFeatured = $this->package('visible-featured-' . strtolower(self::uniqueSuffix()), $city, 1, true, true, true);
        $hidden = $this->package('hidden-' . strtolower(self::uniqueSuffix()), $city, 1000, false, true, false);
        $inactive = $this->package('inactive-' . strtolower(self::uniqueSuffix()), $city, 1000, false, false, true);

        $result = self::getContainer()->get(TourPackageRepository::class)->findActivePublicVisible($city);

        self::assertSame($visibleFeatured->getId(), $result[0]->getId());
        self::assertSame($visibleLow->getId(), $result[1]->getId());
        self::assertNotContains($hidden->getId(), array_map(static fn (TourPackage $package): ?int => $package->getId(), $result));
        self::assertNotContains($inactive->getId(), array_map(static fn (TourPackage $package): ?int => $package->getId(), $result));
    }

    private function runBootstrap(): CommandTester
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:bootstrap');
        $tester = new CommandTester($command);
        $tester->execute([]);

        return $tester;
    }

    /**
     * @return array{country: Country, istanbul: City, cologne: City, cgn: Airport, ist: Airport, airline: Airline, hotel: Hotel, room: HotelRoomType}
     */
    private function catalogData(): array
    {
        $suffix = self::uniqueSuffix();
        $country = (new Country())->setName('Tour Test Country ' . $suffix);
        $istanbul = (new City())->setCountry($country)->setName('Istanbul ' . $suffix)->setSlug('istanbul-' . strtolower($suffix));
        $cologne = (new City())->setCountry($country)->setName('Cologne ' . $suffix)->setSlug('cologne-' . strtolower($suffix));
        $cgn = $this->airport('CGN', $cologne, 'Cologne Bonn ' . $suffix);
        $ist = $this->airport('IST', $istanbul, 'Istanbul Airport ' . $suffix);
        $airline = (new Airline())->setName('Turkish Airlines ' . $suffix);
        $hotel = (new Hotel())->setCity($istanbul)->setName('Arts Hotel Istanbul Harbiye')->setSlug('arts-hotel-istanbul-harbiye-' . strtolower($suffix));
        $room = (new HotelRoomType())->setHotel($hotel)->setName('Imported Double Room ' . $suffix)->setCode('dbl-' . strtolower($suffix));

        foreach ([$country, $istanbul, $cologne, $airline, $hotel, $room] as $entity) {
            $this->em()->persist($entity);
        }
        if ($cgn->getId() === null) {
            $this->em()->persist($cgn);
        }
        if ($ist->getId() === null) {
            $this->em()->persist($ist);
        }
        $this->em()->flush();

        return compact('country', 'istanbul', 'cologne', 'cgn', 'ist', 'airline', 'hotel', 'room');
    }

    private function airport(string $iataCode, City $city, string $name): Airport
    {
        $existing = $this->em()->getRepository(Airport::class)->findOneBy(['iataCode' => $iataCode]);
        if ($existing instanceof Airport) {
            return $existing;
        }

        return (new Airport())
            ->setCity($city)
            ->setName($name)
            ->setIataCode($iataCode);
    }

    private function ownFlightOffer(Airport $origin, Airport $destination, Airline $airline): FlightOffer
    {
        $offer = (new FlightOffer())
            ->setSourceType(FlightPriceSourceType::OWN)
            ->setTripType(FlightTripType::ROUND_TRIP)
            ->setPricingMode(FlightPricingMode::PER_PASSENGER_TYPE)
            ->setAdults(2)
            ->setChildren(1)
            ->setInfants(0)
            ->setCurrency('EUR')
            ->setAdultPrice('280.00')
            ->setChildPrice('220.00')
            ->setPriority(100);

        $offer
            ->addLeg((new FlightOfferLeg())->setAirline($airline)->setDirection(FlightDirection::OUTBOUND)->setSegmentIndex(0)->setOriginAirport($origin)->setDestinationAirport($destination)->setFlightNumber('TK1672')->setDepartureAt(new \DateTimeImmutable('2026-09-10 10:20'))->setArrivalAt(new \DateTimeImmutable('2026-09-10 14:25')))
            ->addLeg((new FlightOfferLeg())->setAirline($airline)->setDirection(FlightDirection::INBOUND)->setSegmentIndex(0)->setOriginAirport($destination)->setDestinationAirport($origin)->setFlightNumber('TK1671')->setDepartureAt(new \DateTimeImmutable('2026-09-15 15:25'))->setArrivalAt(new \DateTimeImmutable('2026-09-15 18:00')));

        $this->em()->persist($offer);
        $this->em()->flush();

        return $offer;
    }

    private function package(string $slug, City $city, int $priority, bool $featured, bool $active, bool $publicVisible): TourPackage
    {
        $package = (new TourPackage())
            ->setName($slug)
            ->setSlug($slug)
            ->setDestinationCity($city)
            ->setValidFrom(new \DateTimeImmutable('2026-09-01'))
            ->setValidTo(new \DateTimeImmutable('2026-09-30'))
            ->setNights(5)
            ->setAdults(2)
            ->setChildren(0)
            ->setInfants(0)
            ->setPricingMode(\App\Modules\Tour\Enum\TourPricingMode::TOTAL_PARTY)
            ->setCurrency('EUR')
            ->setTotalPrice('1490.00')
            ->setPriority($priority)
            ->setFeatured($featured)
            ->setActive($active)
            ->setPublicVisible($publicVisible);

        $this->em()->persist($package);
        $this->em()->flush();

        return $package;
    }

    private function createSuperAdminUser(): UserEntity
    {
        $em = $this->em();
        $role = $em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);
        if (!$role instanceof Role) {
            $role = (new Role())->setName('ROLE_SUPER_ADMIN');
            $em->persist($role);
        }

        $user = (new UserEntity())
            ->setEmail('tour-admin-' . strtolower(self::uniqueSuffix()) . '@example.test')
            ->setPassword('not-used')
            ->addUserRole($role);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function formToken(Crawler $crawler, string $formName): string
    {
        return $crawler->filter(sprintf('input[name="%s[_token]"]', $formName))->attr('value') ?? '';
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
