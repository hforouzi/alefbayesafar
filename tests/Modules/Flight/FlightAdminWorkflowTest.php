<?php

namespace App\Tests\Modules\Flight;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Flight\Entity\Airline;
use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Entity\FlightOfferLeg;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\User\Entity\Role;
use App\Modules\User\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\RouterInterface;

class FlightAdminWorkflowTest extends WebTestCase
{
    public function testFlightCommerceRoutesExistAndAnonymousUserRedirects(): void
    {
        $client = self::createClient();
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);

        foreach ([
            'flight_airline_index',
            'flight_airline_new',
            'flight_airline_edit',
            'flight_airline_delete',
            'flight_lookup_airlines',
            'flight_external_test',
            'flight_offer_index',
            'flight_offer_new',
            'flight_offer_edit',
            'flight_offer_toggle',
            'flight_offer_leg_index',
            'flight_offer_leg_new',
            'flight_offer_leg_edit',
            'flight_offer_leg_delete',
        ] as $routeName) {
            self::assertNotNull($router->getRouteCollection()->get($routeName), $routeName);
        }

        $client->request('GET', '/admin/flight-commerce/own-deals/');

        self::assertResponseRedirects('/login');
    }

    public function testAdminCanCreateRoundTripOwnDealManageLegsListEditAndToggle(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());

        $application = new Application(self::$kernel);
        $command = $application->find('app:flight:seed-admin');
        self::assertSame(0, (new CommandTester($command))->execute([]));

        $suffix = self::uniqueSuffix();
        $cgn = $this->persistAirport('CGN', 'Cologne Bonn ' . $suffix, 'Cologne ' . $suffix);
        $ist = $this->persistAirport('IST', 'Istanbul ' . $suffix, 'Istanbul ' . $suffix);
        $airline = $this->persistAirline($suffix);

        $crawler = $client->request('GET', '/admin/flight-commerce/own-deals/new');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/admin/flight-commerce/own-deals/new', [
            'flight_offer' => [
                '_token' => $this->formToken($crawler, 'flight_offer'),
                'tripType' => 'round_trip',
                'pricingMode' => 'per_passenger_type',
                'adults' => '2',
                'children' => '1',
                'infants' => '0',
                'cabinClass' => 'economy',
                'currency' => 'EUR',
                'adultPrice' => '280',
                'childPrice' => '220',
                'infantPrice' => '',
                'totalPrice' => '',
                'baggage' => '20 kg checked + 8 kg cabin',
                'availabilityStatus' => 'available',
                'priority' => '100',
                'validFrom' => '',
                'validTo' => '',
                'active' => '1',
            ],
        ]);

        $offer = $this->entityManager()->getRepository(FlightOffer::class)->findOneBy(['priority' => 100], ['id' => 'DESC']);
        self::assertInstanceOf(FlightOffer::class, $offer);
        self::assertResponseRedirects('/admin/flight-commerce/own-deals/' . $offer->getId() . '/legs');

        $this->postLeg($client, $offer, $airline, $cgn, $ist, 'outbound', 0, 'TK1672', '2026-09-10T10:20', '2026-09-10T14:25');
        $this->postLeg($client, $offer, $airline, $ist, $cgn, 'inbound', 0, 'TK1671', '2026-09-17T15:25', '2026-09-17T18:00');

        $this->entityManager()->clear();
        $stored = $this->entityManager()->getRepository(FlightOffer::class)->find($offer->getId());
        self::assertInstanceOf(FlightOffer::class, $stored);
        self::assertSame('CGN -> IST', $stored->getRouteLabel());
        self::assertSame('Adult 280.00 EUR / Child 220.00 EUR', $stored->getPriceLabel());

        $client->request('GET', '/admin/flight-commerce/own-deals/', ['q' => 'TK1672']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'CGN -> IST');
        self::assertSelectorTextContains('body', 'Turkish Airlines ' . $suffix);
        self::assertSelectorTextContains('body', 'Adult 280.00 EUR / Child 220.00 EUR');

        $crawler = $client->request('GET', '/admin/flight-commerce/own-deals/' . $stored->getId() . '/edit');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/admin/flight-commerce/own-deals/' . $stored->getId() . '/edit', [
            'flight_offer' => [
                '_token' => $this->formToken($crawler, 'flight_offer'),
                'tripType' => 'round_trip',
                'pricingMode' => 'per_passenger_type',
                'adults' => '2',
                'children' => '1',
                'infants' => '0',
                'cabinClass' => 'economy',
                'currency' => 'EUR',
                'adultPrice' => '290',
                'childPrice' => '230',
                'infantPrice' => '',
                'totalPrice' => '',
                'baggage' => '20 kg checked',
                'availabilityStatus' => 'available',
                'priority' => '90',
                'validFrom' => '',
                'validTo' => '',
                'active' => '1',
            ],
        ]);
        self::assertResponseRedirects('/admin/flight-commerce/own-deals/');

        $crawler = $client->request('GET', '/admin/flight-commerce/own-deals/');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/admin/flight-commerce/own-deals/' . $stored->getId() . '/toggle', [
            '_token' => $crawler
                ->filter(sprintf('form[action="/admin/flight-commerce/own-deals/%d/toggle"] input[name="_token"]', $stored->getId()))
                ->attr('value'),
        ]);
        self::assertResponseRedirects('/admin/flight-commerce/own-deals/');

        $this->entityManager()->clear();
        $toggled = $this->entityManager()->getRepository(FlightOffer::class)->find($stored->getId());
        self::assertInstanceOf(FlightOffer::class, $toggled);
        self::assertFalse($toggled->isActive());
    }

    public function testExternalFlightTestShowsNoSourcesAndProviderError(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());
        $this->disableFlightSources();

        $crawler = $client->request('GET', '/admin/flight-commerce/external-test/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'No enabled external flight sources are configured.');

        $suffix = self::uniqueSuffix();
        $cgn = $this->persistAirport('CGN', 'Cologne Bonn ' . $suffix, 'Cologne ' . $suffix);
        $ist = $this->persistAirport('IST', 'Istanbul ' . $suffix, 'Istanbul ' . $suffix);
        $this->persistUnsupportedFlightSource($suffix);

        $client->request('POST', '/admin/flight-commerce/external-test/', [
            'external_flight_test' => [
                '_token' => $this->formToken($crawler, 'external_flight_test'),
                'originAirportId' => (string) $cgn->getId(),
                'destinationAirportId' => (string) $ist->getId(),
                'departureDate' => '2026-09-10',
                'returnDate' => '',
                'adults' => '2',
                'children' => '0',
                'infants' => '0',
                'cabinClass' => 'economy',
                'directOnly' => '1',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'PROVIDER_ERROR');
    }

    private function postLeg($client, FlightOffer $offer, Airline $airline, Airport $origin, Airport $destination, string $direction, int $index, string $flightNumber, string $departure, string $arrival): void
    {
        $crawler = $client->request('GET', '/admin/flight-commerce/own-deals/' . $offer->getId() . '/legs/new');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/admin/flight-commerce/own-deals/' . $offer->getId() . '/legs/new', [
            'flight_offer_leg' => [
                '_token' => $this->formToken($crawler, 'flight_offer_leg'),
                'airlineId' => (string) $airline->getId(),
                'originAirportId' => (string) $origin->getId(),
                'destinationAirportId' => (string) $destination->getId(),
                'direction' => $direction,
                'segmentIndex' => (string) $index,
                'flightNumber' => $flightNumber,
                'departureAt' => $departure,
                'arrivalAt' => $arrival,
                'durationMinutes' => '',
                'aircraft' => '',
            ],
        ]);

        self::assertResponseRedirects('/admin/flight-commerce/own-deals/' . $offer->getId() . '/legs');
        self::assertNotNull($this->entityManager()->getRepository(FlightOfferLeg::class)->findOneBy(['flightNumber' => $flightNumber]));
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
            ->setEmail('flight-admin-' . strtolower(self::uniqueSuffix()) . '@example.test')
            ->setPassword('not-used')
            ->addUserRole($role);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function persistAirline(string $suffix): Airline
    {
        $airline = (new Airline())
            ->setName('Turkish Airlines ' . $suffix);

        $em = $this->entityManager();
        $em->persist($airline);
        $em->flush();

        return $airline;
    }

    private function persistAirport(string $iataCode, string $airportName, string $cityName): Airport
    {
        $existing = $this->entityManager()->getRepository(Airport::class)->findOneBy(['iataCode' => $iataCode]);
        if ($existing instanceof Airport) {
            return $existing;
        }

        $suffix = self::uniqueSuffix();
        $country = (new Country())
            ->setName('Flight Test Country ' . $iataCode . ' ' . $suffix);
        $city = (new City())
            ->setCountry($country)
            ->setName($cityName);
        $airport = (new Airport())
            ->setCity($city)
            ->setName($airportName)
            ->setIataCode($iataCode);

        $em = $this->entityManager();
        foreach ([$country, $city, $airport] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        return $airport;
    }

    private function disableFlightSources(): void
    {
        foreach ($this->entityManager()->getRepository(SearchSource::class)->findAll() as $source) {
            if ($source instanceof SearchSource && $source->supports(SearchSource::CAPABILITY_FLIGHT)) {
                $source->setEnabled(false);
            }
        }
        $this->entityManager()->flush();
    }

    private function persistUnsupportedFlightSource(string $suffix): SearchSource
    {
        $source = (new SearchSource())
            ->setName('Unsupported Flight Source ' . $suffix)
            ->setDomain('unsupported-' . strtolower($suffix) . '.example.test')
            ->setProvider('unsupported_provider')
            ->setProviderType(SearchSourceProviderType::SCRAPER)
            ->setCapabilities([SearchSource::CAPABILITY_FLIGHT])
            ->setConfig([]);

        $this->entityManager()->persist($source);
        $this->entityManager()->flush();

        return $source;
    }

    private function entityManager(): EntityManagerInterface
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
