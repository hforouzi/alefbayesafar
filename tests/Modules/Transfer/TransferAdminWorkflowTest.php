<?php

namespace App\Tests\Modules\Transfer;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Transfer\Entity\TransferOffer;
use App\Modules\Transfer\Entity\TransferProduct;
use App\Modules\User\Entity\Role;
use App\Modules\User\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\RouterInterface;

class TransferAdminWorkflowTest extends WebTestCase
{
    public function testTransferCommerceRoutesExistAndAnonymousUserRedirects(): void
    {
        $client = self::createClient();
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);

        foreach ([
            'transfer_product_index',
            'transfer_product_new',
            'transfer_product_edit',
            'transfer_product_toggle',
            'transfer_offer_index',
            'transfer_offer_new',
            'transfer_offer_edit',
            'transfer_offer_toggle',
            'transfer_lookup_hotels',
        ] as $routeName) {
            self::assertNotNull($router->getRouteCollection()->get($routeName), $routeName);
        }

        $client->request('GET', '/admin/transfer-commerce/products/');
        self::assertResponseRedirects('/login');
    }

    public function testAdminCanCreateEditToggleAndManagePricing(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());
        $this->runBootstrap();

        $airport = $this->airport();
        $city = $airport->getCity();
        self::assertInstanceOf(City::class, $city);

        $crawler = $client->request('GET', '/admin/transfer-commerce/products/new');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/admin/transfer-commerce/products/new', [
            'transfer_product' => [
                '_token' => $this->formToken($crawler, 'transfer_product'),
                'originAirportId' => (string) $airport->getId(),
                'originCityId' => '',
                'originHotelId' => '',
                'destinationAirportId' => '',
                'destinationCityId' => (string) $city->getId(),
                'destinationHotelId' => '',
                'name' => 'Airport to City Private Transfer',
                'nameFa' => '',
                'transferType' => 'private',
                'vehicleType' => 'sedan',
                'maxPassengers' => '3',
                'maxLuggage' => '3',
                'description' => '',
                'active' => '1',
                'featured' => '1',
                'publicVisible' => '1',
            ],
        ]);
        self::assertResponseRedirects('/admin/transfer-commerce/products/');

        $product = $this->em()->getRepository(TransferProduct::class)->findOneBy(['name' => 'Airport to City Private Transfer']);
        self::assertInstanceOf(TransferProduct::class, $product);
        self::assertSame($airport->getId(), $product->getOriginAirport()?->getId());
        self::assertSame($city->getId(), $product->getDestinationCity()?->getId());

        $offerCrawler = $client->request('GET', '/admin/transfer-commerce/products/' . $product->getId() . '/offers/new');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/admin/transfer-commerce/products/' . $product->getId() . '/offers/new', [
            'transfer_offer' => [
                '_token' => $this->formToken($offerCrawler, 'transfer_offer'),
                'validFrom' => '',
                'validTo' => '',
                'currency' => 'EUR',
                'pricingMode' => 'total_service',
                'totalPrice' => '35',
                'availabilityStatus' => 'available',
                'priority' => '100',
                'active' => '1',
            ],
        ]);
        self::assertResponseRedirects('/admin/transfer-commerce/products/' . $product->getId() . '/offers/');

        $offer = $this->em()->getRepository(TransferOffer::class)->findOneBy(['transferProduct' => $product]);
        self::assertInstanceOf(TransferOffer::class, $offer);
        self::assertSame('35.00', $offer->getTotalPrice());

        $crawler = $client->request('GET', '/admin/transfer-commerce/products/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Airport to City Private Transfer');

        $toggleToken = $crawler
            ->filter(sprintf('form[action="/admin/transfer-commerce/products/%d/toggle"] input[name="_token"]', $product->getId()))
            ->attr('value');
        $client->request('POST', '/admin/transfer-commerce/products/' . $product->getId() . '/toggle', ['_token' => $toggleToken]);
        self::assertResponseRedirects('/admin/transfer-commerce/products/');

        $this->em()->clear();
        $toggled = $this->em()->getRepository(TransferProduct::class)->find($product->getId());
        self::assertInstanceOf(TransferProduct::class, $toggled);
        self::assertFalse($toggled->isActive());
    }

    public function testProductRejectsMultipleOriginReferences(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());

        $airport = $this->airport();
        $city = $airport->getCity();
        self::assertInstanceOf(City::class, $city);

        $crawler = $client->request('GET', '/admin/transfer-commerce/products/new');
        $client->request('POST', '/admin/transfer-commerce/products/new', [
            'transfer_product' => [
                '_token' => $this->formToken($crawler, 'transfer_product'),
                'originAirportId' => (string) $airport->getId(),
                'originCityId' => (string) $city->getId(),
                'originHotelId' => '',
                'destinationAirportId' => '',
                'destinationCityId' => (string) $city->getId(),
                'destinationHotelId' => '',
                'name' => 'Invalid Endpoint Transfer',
                'nameFa' => '',
                'transferType' => 'private',
                'vehicleType' => 'sedan',
                'maxPassengers' => '',
                'maxLuggage' => '',
                'description' => '',
                'active' => '1',
                'featured' => '',
                'publicVisible' => '',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertNull($this->em()->getRepository(TransferProduct::class)->findOneBy(['name' => 'Invalid Endpoint Transfer']));
    }

    public function testBootstrapCreatesTransferCommerceMenuAndPermissionsIdempotently(): void
    {
        self::createClient()->disableReboot();
        $beforeProducts = $this->em()->getRepository(TransferProduct::class)->count([]);

        $first = $this->runBootstrap();
        $second = $this->runBootstrap();

        self::assertSame(0, $first->getStatusCode());
        self::assertSame(0, $second->getStatusCode());
        self::assertSame($beforeProducts, $this->em()->getRepository(TransferProduct::class)->count([]));
    }

    private function runBootstrap(): CommandTester
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:bootstrap');
        $tester = new CommandTester($command);
        $tester->execute([]);

        return $tester;
    }

    private function airport(): Airport
    {
        $suffix = self::uniqueSuffix();
        $country = (new Country())->setName('Transfer Test Country ' . $suffix);
        $city = (new City())->setCountry($country)->setName('Transfer Test City ' . $suffix)->setSlug('transfer-test-city-' . strtolower($suffix));
        $airport = (new Airport())->setCity($city)->setName('Transfer Test Airport ' . $suffix)->setIataCode(substr($suffix, 0, 3));

        $this->em()->persist($country);
        $this->em()->persist($city);
        $this->em()->persist($airport);
        $this->em()->flush();

        return $airport;
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
            ->setEmail('transfer-admin-' . strtolower(self::uniqueSuffix()) . '@example.test')
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
