<?php

namespace App\Tests\Modules\Destination;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Entity\DestinationImportRun;
use App\Modules\Destination\Entity\District;
use App\Modules\Destination\Entity\State;
use App\Shared\Date\LocaleDateTimeFormatter;
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
            'destination_state_index',
            'destination_state_new',
            'destination_state_edit',
            'destination_state_delete',
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
            'destination_import_index',
            'destination_import_bootstrap',
            'destination_import_enrichment_airports',
            'destination_import_enrichment_travel_areas',
            'destination_lookup_countries',
            'destination_lookup_states',
            'destination_lookup_cities',
            'destination_lookup_districts',
            'destination_lookup_airports',
        ] as $routeName) {
            self::assertNotNull($router->getRouteCollection()->get($routeName), $routeName);
        }
    }

    public function testAnonymousDestinationAdminAccessRedirectsToLogin(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/catalog/countries/');

        self::assertResponseRedirects('/login');

        $client->request('GET', '/admin/catalog/import/');

        self::assertResponseRedirects('/login');
    }

    public function testImportPageRendersForSuperAdmin(): void
    {
        $client = self::createClient();
        $client->loginUser($this->createSuperAdminUser());

        $client->request('GET', '/admin/catalog/import/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'همه فرودگاه‌ها');
        self::assertSelectorTextNotContains('body', 'Import / Refresh ' . 'Istanbul Airports');
        self::assertSelectorTextNotContains('body', 'فرودگاه‌های ' . 'استانبول');
        self::assertSelectorExists('form[action$="/admin/catalog/import/bootstrap/airports"]');
        self::assertSelectorNotExists('form[action$="/admin/catalog/import/bootstrap/airports' . '_turkey"]');
        self::assertSelectorExists('form[action$="/admin/catalog/import/enrichment/airports"]');
        self::assertSelectorExists('script[type="importmap"]');
    }

    public function testCountryCrud(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());
        $suffix = self::uniqueSuffix();
        [$iso2, $iso3] = $this->unusedIsoCodes();

        $crawler = $client->request('GET', '/admin/catalog/countries/new');
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('form')->form([
            'country[name]' => 'Test Country ' . $suffix,
            'country[nameFa]' => 'کشور تست',
            'country[iso2]' => $iso2,
            'country[iso3]' => $iso3,
            'country[active]' => '1',
        ]));
        self::assertResponseRedirects('/admin/catalog/countries/');

        $country = $this->entityManager()->getRepository(Country::class)->findOneBy(['name' => 'Test Country ' . $suffix]);
        self::assertInstanceOf(Country::class, $country);
        self::assertSame($iso2, $country->getIso2());

        $crawler = $client->request('GET', sprintf('/admin/catalog/countries/%d/edit', $country->getId()));
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('form')->form([
            'country[name]' => 'Updated Country ' . $suffix,
            'country[nameFa]' => 'کشور ویرایش',
            'country[iso2]' => $iso2,
            'country[iso3]' => $iso3,
            'country[active]' => '1',
        ]));
        self::assertResponseRedirects('/admin/catalog/countries/');

        $updatedCountry = $this->entityManager()->getRepository(Country::class)->find($country->getId());
        self::assertInstanceOf(Country::class, $updatedCountry);
        self::assertSame('Updated Country ' . $suffix, $updatedCountry->getName());

        $crawler = $client->request('GET', '/admin/catalog/countries/', ['q' => 'Updated Country ' . $suffix]);
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
        $state = (new State())
            ->setCountry($country)
            ->setName('Test State ' . $suffix)
            ->setCode('T' . substr($suffix, 0, 2))
            ->setSlug('test-state-' . strtolower($suffix));
        $this->entityManager()->persist($state);
        $this->entityManager()->flush();

        $crawler = $client->request('GET', '/admin/catalog/cities/new');
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('form')->form([
            'city[country]' => (string) $country->getId(),
            'city[state]' => (string) $state->getId(),
            'city[name]' => 'Test City ' . $suffix,
            'city[nameFa]' => 'شهر تست',
            'city[slug]' => 'test-city-' . strtolower($suffix),
            'city[active]' => '1',
        ]));
        self::assertResponseRedirects('/admin/catalog/cities/');

        $city = $this->entityManager()->getRepository(City::class)->findOneBy(['name' => 'Test City ' . $suffix]);
        self::assertInstanceOf(City::class, $city);
        self::assertSame($country->getId(), $city->getCountry()?->getId());
        self::assertSame($state->getId(), $city->getState()?->getId());

        $crawler = $client->request('GET', '/admin/catalog/districts/new');
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('form')->form([
            'district[cityId]' => (string) $city->getId(),
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
            'airport[cityId]' => (string) $city->getId(),
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

        $crawler = $client->request('GET', '/admin/catalog/districts/', ['q' => 'Test District ' . $suffix]);
        $client->request('POST', sprintf('/admin/catalog/districts/%d/delete', $district->getId()), [
            '_token' => $this->deleteToken($crawler, $district->getId()),
        ]);
        self::assertResponseRedirects('/admin/catalog/districts/');
        self::assertNull($this->entityManager()->getRepository(District::class)->find($district->getId()));

        $crawler = $client->request('GET', '/admin/catalog/airports/', ['q' => 'Test Airport ' . $suffix]);
        $client->request('POST', sprintf('/admin/catalog/airports/%d/delete', $airport->getId()), [
            '_token' => $this->deleteToken($crawler, $airport->getId()),
        ]);
        self::assertResponseRedirects('/admin/catalog/airports/');
        self::assertNull($this->entityManager()->getRepository(Airport::class)->find($airport->getId()));

        $crawler = $client->request('GET', '/admin/catalog/cities/', ['q' => 'Test City ' . $suffix]);
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

        $crawler = $client->request('GET', '/admin/catalog/cities/', ['q' => 'Protected City ' . $suffix]);
        self::assertResponseIsSuccessful();

        $client->request('POST', sprintf('/admin/catalog/cities/%d/delete', $city->getId()), [
            '_token' => $this->deleteToken($crawler, $city->getId()),
        ]);
        self::assertResponseRedirects('/admin/catalog/cities/');

        $deactivatedCity = $em->getRepository(City::class)->find($city->getId());
        self::assertInstanceOf(City::class, $deactivatedCity);
        self::assertFalse($deactivatedCity->isActive());
    }

    public function testLookupEndpointsAreBoundedAndFilterByHierarchy(): void
    {
        $client = self::createClient();
        $client->loginUser($this->createSuperAdminUser());
        $suffix = self::uniqueSuffix();
        $country = $this->persistCountry($suffix);
        $state = (new State())
            ->setCountry($country)
            ->setName('Lookup State ' . $suffix)
            ->setCode('L' . substr($suffix, 0, 2))
            ->setSlug('lookup-state-' . strtolower($suffix));
        $city = (new City())
            ->setCountry($country)
            ->setState($state)
            ->setName('Lookup City ' . $suffix)
            ->setNameFa('شهر جستجو')
            ->setSlug('lookup-city-' . strtolower($suffix));
        $district = (new District())
            ->setCity($city)
            ->setName('Lookup District ' . $suffix)
            ->setSlug('lookup-district-' . strtolower($suffix));
        $airport = (new Airport())
            ->setCity($city)
            ->setName('Lookup Airport ' . $suffix)
            ->setIataCode(substr($suffix, 0, 3))
            ->setIcaoCode('B' . substr($suffix, 0, 3));
        $em = $this->entityManager();
        foreach ([$state, $city, $district, $airport] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $client->request('GET', '/admin/catalog/lookup/cities', [
            'q' => 'جستجو',
            'country' => $country->getId(),
            'state' => $state->getId(),
        ]);
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertCount(1, $payload['results']);
        self::assertStringContainsString('Lookup City', $payload['results'][0]['text']);

        $client->request('GET', '/admin/catalog/lookup/airports', ['q' => substr($suffix, 0, 3)]);
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertLessThanOrEqual(25, \count($payload['results']));
        self::assertStringContainsString('Lookup Airport', $payload['results'][0]['text']);
    }

    public function testDestinationTablesUseServerSideFiltersAndPagination(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());
        $suffix = self::uniqueSuffix();
        $country = $this->persistCountry($suffix);
        $country->setNameFa('کشور ' . $suffix);
        $state = (new State())
            ->setCountry($country)
            ->setName('Paged State ' . $suffix)
            ->setNameFa('استان ' . $suffix)
            ->setCode('P' . substr($suffix, 0, 2))
            ->setSlug('paged-state-' . strtolower($suffix));
        $city = (new City())
            ->setCountry($country)
            ->setState($state)
            ->setName('Paged City ' . $suffix)
            ->setNameFa('شهر ' . $suffix)
            ->setSlug('paged-city-' . strtolower($suffix));
        $district = (new District())
            ->setCity($city)
            ->setName('Paged District ' . $suffix)
            ->setNameFa('محله ' . $suffix)
            ->setSlug('paged-district-' . strtolower($suffix));
        $iataCode = $this->unusedIataCode();
        $airport = (new Airport())
            ->setCity($city)
            ->setName('Paged Airport ' . $suffix)
            ->setNameFa('فرودگاه ' . $suffix)
            ->setIataCode($iataCode)
            ->setIcaoCode('C' . $iataCode);
        $run = (new DestinationImportRun())
            ->setTargetType('city')
            ->setCountryName('Paged Country ' . $suffix)
            ->setCityName('Paged City ' . $suffix)
            ->setProviders(['geonames'])
            ->setRefresh(true)
            ->setFoundCount(1)
            ->finish(DestinationImportRun::STATUS_COMPLETED);

        $em = $this->entityManager();
        foreach ([$state, $city, $district, $airport, $run] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        foreach ([
            '/admin/catalog/countries/' => 'کشور ' . $suffix,
            '/admin/catalog/states/' => 'استان ' . $suffix,
            '/admin/catalog/cities/' => 'شهر ' . $suffix,
            '/admin/catalog/districts/' => 'محله ' . $suffix,
            '/admin/catalog/airports/' => $iataCode,
            '/admin/catalog/import/' => 'Paged City ' . $suffix,
        ] as $path => $query) {
            $client->request('GET', $path, ['q' => $query, 'pageSize' => 1]);
            self::assertResponseIsSuccessful($path);
            self::assertSelectorExists('input[name="q"]', $path);
            self::assertSelectorExists('select[name="pageSize"]', $path);
            self::assertSelectorTextContains('body', (string) $query, $path);
            self::assertSelectorTextContains('body', 'صفحه', $path);
        }
    }

    public function testDestinationTablesUseColumnFiltersAndFullPagination(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());
        $suffix = self::uniqueSuffix();
        [$iso2, $iso3] = $this->unusedIsoCodes();
        $country = (new Country())
            ->setName('Filter Country ' . $suffix)
            ->setNameFa('Persian Country ' . $suffix)
            ->setIso2($iso2)
            ->setIso3($iso3);
        $inactiveCountry = (new Country())
            ->setName('Inactive Filter Country ' . $suffix)
            ->setIso2($this->unusedIsoCodes()[0])
            ->setActive(false);
        $state = (new State())
            ->setCountry($country)
            ->setName('Filter State ' . $suffix)
            ->setNameFa('Persian State ' . $suffix)
            ->setCode('P' . substr($suffix, 0, 2))
            ->setSlug('filter-state-' . strtolower($suffix));
        $city = (new City())
            ->setCountry($country)
            ->setState($state)
            ->setName('Filter City ' . $suffix)
            ->setNameFa('Persian City ' . $suffix)
            ->setSlug('filter-city-' . strtolower($suffix));
        $district = (new District())
            ->setCity($city)
            ->setName('Filter District ' . $suffix)
            ->setNameFa('Persian District ' . $suffix)
            ->setSlug('filter-district-' . strtolower($suffix));
        $iataCode = $this->unusedIataCode();
        $airport = (new Airport())
            ->setCity($city)
            ->setName('Filter Airport ' . $suffix)
            ->setNameFa('Persian Airport ' . $suffix)
            ->setIataCode($iataCode)
            ->setIcaoCode('C' . $iataCode);
        $run = (new DestinationImportRun())
            ->setTargetType('city')
            ->setCountryName('Filter Country ' . $suffix)
            ->setCityName('Filter City ' . $suffix)
            ->setProviders(['geonames'])
            ->setRefresh(true)
            ->setFoundCount(1)
            ->finish(DestinationImportRun::STATUS_COMPLETED);

        $em = $this->entityManager();
        foreach ([$country, $inactiveCountry, $state, $city, $district, $airport, $run] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        for ($i = 1; $i <= 30; $i++) {
            $em->persist((new Country())
                ->setName(sprintf('Pagination Country %s %02d', $suffix, $i)));
        }
        $em->flush();

        $client->request('GET', '/admin/catalog/countries/', ['name' => 'Filter Country ' . $suffix, 'iso2' => $iso2, 'active' => '1', 'pageSize' => 25]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="name"]');
        self::assertSelectorExists('input[name="iso2"]');
        self::assertSelectorExists('select[name="active"]');
        self::assertSelectorTextContains('body', 'Filter Country ' . $suffix);
        self::assertSelectorTextNotContains('body', 'Inactive Filter Country ' . $suffix);

        $client->request('GET', '/admin/catalog/countries/', ['nameFa' => 'Persian Country ' . $suffix, 'iso3' => $iso3]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Persian Country ' . $suffix);

        $client->request('GET', '/admin/catalog/states/', ['name' => 'Filter State ' . $suffix, 'country' => $country->getId(), 'code' => 'P' . substr($suffix, 0, 2)]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="country"]');
        self::assertSelectorTextContains('body', 'Filter State ' . $suffix);

        $client->request('GET', '/admin/catalog/cities/', ['name' => 'Filter City ' . $suffix, 'nameFa' => 'Persian City ' . $suffix, 'country' => $country->getId(), 'state' => $state->getId(), 'active' => '1']);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="state"]');
        self::assertSelectorTextContains('body', 'Filter City ' . $suffix);

        $client->request('GET', '/admin/catalog/districts/', ['name' => 'Filter District ' . $suffix, 'city' => $city->getId()]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Filter District ' . $suffix);

        $client->request('GET', '/admin/catalog/airports/', ['name' => 'Filter Airport ' . $suffix, 'iata' => $iataCode, 'icao' => 'C' . $iataCode, 'country' => $country->getId(), 'city' => $city->getId()]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="iata"]');
        self::assertSelectorExists('input[name="icao"]');
        self::assertSelectorTextContains('body', 'Filter Airport ' . $suffix);

        $client->request('GET', '/admin/catalog/import/', ['provider' => 'geonames', 'targetType' => 'city', 'country' => 'Filter Country ' . $suffix, 'status' => DestinationImportRun::STATUS_COMPLETED, 'startedFrom' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d')]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="provider"]');
        self::assertSelectorExists('input[name="startedFrom"][type="hidden"]');
        self::assertSelectorExists('input[name="startedFrom_display"][type="text"]');
        self::assertSelectorExists('[data-controller="locale-date"]');
        self::assertSelectorTextContains('body', 'Filter City ' . $suffix);

        /** @var LocaleDateTimeFormatter $formatter */
        $formatter = self::getContainer()->get(LocaleDateTimeFormatter::class);
        $client->request('GET', '/admin/catalog/import/', [
            'provider' => 'geonames',
            'targetType' => 'city',
            'startedFrom_display' => $formatter->formatDate(new \DateTimeImmutable(), 'fa'),
        ]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="fa"][dir="rtl"]');
        self::assertSelectorTextContains('body', $formatter->formatDate(new \DateTimeImmutable(), 'fa'));
        self::assertSelectorTextContains('body', 'Filter City ' . $suffix);

        $client->request('GET', '/locale/en', [], [], ['HTTP_REFERER' => 'http://localhost/admin/catalog/import/']);
        $client->request('GET', '/admin/catalog/import/', [
            'provider' => 'geonames',
            'targetType' => 'city',
            'startedFrom' => (new \DateTimeImmutable())->format('Y-m-d'),
        ]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="en"][dir="ltr"]');
        self::assertSelectorTextContains('body', (new \DateTimeImmutable())->format('Y-m-d'));

        $crawler = $client->request('GET', '/admin/catalog/countries/', ['name' => 'Pagination Country ' . $suffix, 'pageSize' => 25, 'page' => 2]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href*="page=1"]');
        self::assertSelectorExists('a[href*="page=2"]');
        self::assertSelectorExists('input[name="page"]');
        self::assertSelectorExists('select[name="pageSize"]');
        self::assertSame('Pagination Country ' . $suffix, $crawler->filter('input[name="name"]')->attr('value'));

        $crawler = $client->request('GET', '/admin/catalog/countries/', ['name' => 'Pagination Country ' . $suffix, 'pageSize' => 25, 'page' => 99]);
        self::assertResponseIsSuccessful();
        self::assertSame('2', $crawler->filter('input[name="page"]')->attr('value'));

        $crawler = $client->request('GET', '/admin/catalog/countries/', ['name' => 'Pagination Country ' . $suffix, 'pageSize' => 10, 'page' => -5]);
        self::assertResponseIsSuccessful();
        self::assertSame('1', $crawler->filter('input[name="page"]')->attr('value'));

        $client->request('GET', '/admin/catalog/countries/', ['name' => 'Pagination Country ' . $suffix, 'pageSize' => 999999]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="pageSize"] option[value="100"][selected]');
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
            ->setName('Parent Country ' . $suffix);

        $em = $this->entityManager();
        $em->persist($country);
        $em->flush();

        return $country;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function unusedIsoCodes(): array
    {
        do {
            $iso2 = chr(random_int(65, 90)) . chr(random_int(65, 90));
            $iso3 = $iso2 . chr(random_int(65, 90));
        } while ($this->entityManager()->getRepository(Country::class)->count(['iso2' => $iso2]) > 0);

        return [$iso2, $iso3];
    }

    private function unusedIataCode(): string
    {
        do {
            $iata = chr(random_int(65, 90)) . chr(random_int(65, 90)) . chr(random_int(65, 90));
        } while ($this->entityManager()->getRepository(Airport::class)->count(['iataCode' => $iata]) > 0);

        return $iata;
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
