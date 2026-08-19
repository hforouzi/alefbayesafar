<?php

namespace App\Tests\Modules\Destination;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Entity\District;
use App\Modules\User\Entity\Role;
use App\Modules\User\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

class DestinationAdminCrudTest extends WebTestCase
{
    public function testDestinationRoutesExist(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get(RouterInterface::class);

        foreach ([
            'destination_country_index',
            'destination_country_new',
            'destination_country_edit',
            'destination_country_delete',
            'destination_city_index',
            'destination_city_new',
            'destination_city_edit',
            'destination_city_delete',
            'destination_district_index',
            'destination_district_new',
            'destination_district_edit',
            'destination_district_delete',
            'destination_airport_index',
            'destination_airport_new',
            'destination_airport_edit',
            'destination_airport_delete',
        ] as $routeName) {
            self::assertNotNull($router->getRouteCollection()->get($routeName), $routeName);
        }
    }

    public function testAnonymousDestinationAdminAccessRedirectsToLogin(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/catalog/countries/');

        self::assertResponseRedirects('/login');
    }

    public function testCountryCrud(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());
        $suffix = self::uniqueSuffix();

        $crawler = $client->request('GET', '/admin/catalog/countries/new');
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('form')->form([
            'country[name]' => 'Test Country ' . $suffix,
            'country[nameFa]' => 'کشور تست',
            'country[iso2]' => substr($suffix, 0, 2),
            'country[iso3]' => substr($suffix, 0, 3),
            'country[active]' => '1',
        ]));
        self::assertResponseRedirects('/admin/catalog/countries/');

        $country = $this->entityManager()->getRepository(Country::class)->findOneBy(['name' => 'Test Country ' . $suffix]);
        self::assertInstanceOf(Country::class, $country);
        self::assertSame(strtoupper(substr($suffix, 0, 2)), $country->getIso2());

        $crawler = $client->request('GET', sprintf('/admin/catalog/countries/%d/edit', $country->getId()));
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('form')->form([
            'country[name]' => 'Updated Country ' . $suffix,
            'country[nameFa]' => 'کشور ویرایش',
            'country[iso2]' => substr($suffix, 0, 2),
            'country[iso3]' => substr($suffix, 0, 3),
            'country[active]' => '1',
        ]));
        self::assertResponseRedirects('/admin/catalog/countries/');

        $updatedCountry = $this->entityManager()->getRepository(Country::class)->find($country->getId());
        self::assertInstanceOf(Country::class, $updatedCountry);
        self::assertSame('Updated Country ' . $suffix, $updatedCountry->getName());

        $crawler = $client->request('GET', '/admin/catalog/countries/');
        $client->request('POST', sprintf('/admin/catalog/countries/%d/delete', $country->getId()), [
            '_token' => $this->deleteToken($crawler, $country->getId()),
        ]);
        self::assertResponseRedirects('/admin/catalog/countries/');
        self::assertNull($this->entityManager()->getRepository(Country::class)->find($country->getId()));
    }

    public function testCityDistrictAndAirportCrud(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());
        $suffix = self::uniqueSuffix();
        $country = $this->persistCountry($suffix);

        $crawler = $client->request('GET', '/admin/catalog/cities/new');
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('form')->form([
            'city[country]' => (string) $country->getId(),
            'city[name]' => 'Test City ' . $suffix,
            'city[nameFa]' => 'شهر تست',
            'city[slug]' => 'test-city-' . strtolower($suffix),
            'city[active]' => '1',
        ]));
        self::assertResponseRedirects('/admin/catalog/cities/');

        $city = $this->entityManager()->getRepository(City::class)->findOneBy(['name' => 'Test City ' . $suffix]);
        self::assertInstanceOf(City::class, $city);
        self::assertSame($country->getId(), $city->getCountry()?->getId());

        $crawler = $client->request('GET', '/admin/catalog/districts/new');
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('form')->form([
            'district[city]' => (string) $city->getId(),
            'district[name]' => 'Test District ' . $suffix,
            'district[nameFa]' => 'محله تست',
            'district[slug]' => 'test-district-' . strtolower($suffix),
            'district[active]' => '1',
        ]));
        self::assertResponseRedirects('/admin/catalog/districts/');
        $district = $this->entityManager()->getRepository(District::class)->findOneBy(['name' => 'Test District ' . $suffix]);
        self::assertInstanceOf(District::class, $district);

        $crawler = $client->request('GET', '/admin/catalog/airports/new');
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('form')->form([
            'airport[city]' => (string) $city->getId(),
            'airport[name]' => 'Test Airport ' . $suffix,
            'airport[nameFa]' => 'فرودگاه تست',
            'airport[iataCode]' => substr($suffix, 0, 3),
            'airport[icaoCode]' => 'A' . substr($suffix, 0, 3),
            'airport[latitude]' => '35.6892000',
            'airport[longitude]' => '51.3890000',
            'airport[active]' => '1',
        ]));
        self::assertResponseRedirects('/admin/catalog/airports/');
        $airport = $this->entityManager()->getRepository(Airport::class)->findOneBy(['name' => 'Test Airport ' . $suffix]);
        self::assertInstanceOf(Airport::class, $airport);
        self::assertSame(strtoupper(substr($suffix, 0, 3)), $airport->getIataCode());

        $crawler = $client->request('GET', '/admin/catalog/districts/');
        $client->request('POST', sprintf('/admin/catalog/districts/%d/delete', $district->getId()), [
            '_token' => $this->deleteToken($crawler, $district->getId()),
        ]);
        self::assertResponseRedirects('/admin/catalog/districts/');
        self::assertNull($this->entityManager()->getRepository(District::class)->find($district->getId()));

        $crawler = $client->request('GET', '/admin/catalog/airports/');
        $client->request('POST', sprintf('/admin/catalog/airports/%d/delete', $airport->getId()), [
            '_token' => $this->deleteToken($crawler, $airport->getId()),
        ]);
        self::assertResponseRedirects('/admin/catalog/airports/');
        self::assertNull($this->entityManager()->getRepository(Airport::class)->find($airport->getId()));

        $crawler = $client->request('GET', '/admin/catalog/cities/');
        $client->request('POST', sprintf('/admin/catalog/cities/%d/delete', $city->getId()), [
            '_token' => $this->deleteToken($crawler, $city->getId()),
        ]);
        self::assertResponseRedirects('/admin/catalog/cities/');
        self::assertNull($this->entityManager()->getRepository(City::class)->find($city->getId()));
    }

    public function testCityWithChildrenIsDeactivatedOnDelete(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());
        $suffix = self::uniqueSuffix();
        $country = $this->persistCountry($suffix);
        $city = (new City())
            ->setCountry($country)
            ->setName('Protected City ' . $suffix)
            ->setSlug('protected-city-' . strtolower($suffix));
        $district = (new District())
            ->setCity($city)
            ->setName('Protected District ' . $suffix)
            ->setSlug('protected-district-' . strtolower($suffix));
        $em = $this->entityManager();
        $em->persist($city);
        $em->persist($district);
        $em->flush();

        $crawler = $client->request('GET', '/admin/catalog/cities/');
        self::assertResponseIsSuccessful();

        $client->request('POST', sprintf('/admin/catalog/cities/%d/delete', $city->getId()), [
            '_token' => $this->deleteToken($crawler, $city->getId()),
        ]);
        self::assertResponseRedirects('/admin/catalog/cities/');

        $deactivatedCity = $em->getRepository(City::class)->find($city->getId());
        self::assertInstanceOf(City::class, $deactivatedCity);
        self::assertFalse($deactivatedCity->isActive());
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
            ->setEmail('phase2-admin-' . strtolower(self::uniqueSuffix()) . '@example.test')
            ->setPassword('not-used')
            ->addUserRole($role);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function deleteToken(Crawler $crawler, int $entityId): string
    {
        return $crawler
            ->filter(sprintf('form[action$="/%d/delete"] input[name="_token"]', $entityId))
            ->attr('value') ?? '';
    }

    private function persistCountry(string $suffix): Country
    {
        $country = (new Country())
            ->setName('Parent Country ' . $suffix)
            ->setIso2(substr($suffix, 0, 2))
            ->setIso3(substr($suffix, 0, 3));

        $em = $this->entityManager();
        $em->persist($country);
        $em->flush();

        return $country;
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
