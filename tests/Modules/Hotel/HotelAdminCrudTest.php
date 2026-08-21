<?php

namespace App\Tests\Modules\Hotel;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Entity\District;
use App\Modules\Destination\Entity\State;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelAmenity;
use App\Modules\User\Entity\Role;
use App\Modules\User\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\RouterInterface;

class HotelAdminCrudTest extends WebTestCase
{
    public function testHotelRoutesExist(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);

        foreach ([
            'hotel_index',
            'hotel_new',
            'hotel_show',
            'hotel_edit',
            'hotel_delete',
            'hotel_amenity_index',
            'hotel_amenity_new',
            'hotel_amenity_edit',
            'hotel_amenity_delete',
            'hotel_image_new',
            'hotel_image_edit',
            'hotel_image_delete',
        ] as $routeName) {
            self::assertNotNull($router->getRouteCollection()->get($routeName), $routeName);
        }
    }

    public function testAnonymousHotelAdminAccessRedirectsToLogin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/catalog/hotels/');

        self::assertResponseRedirects('/login');
    }

    public function testHotelCrudAndImageMetadataManagement(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());
        $suffix = self::uniqueSuffix();
        [$city, $district] = $this->persistGeography($suffix);
        $amenity = (new HotelAmenity())
            ->setName('Pool ' . $suffix)
            ->setCode('pool-' . strtolower($suffix));
        $em = $this->entityManager();
        $em->persist($amenity);
        $em->flush();

        $crawler = $client->request('GET', '/admin/catalog/hotels/new');
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('form')->form([
            'hotel[cityId]' => (string) $city->getId(),
            'hotel[districtId]' => (string) $district->getId(),
            'hotel[name]' => 'Test Hotel ' . $suffix,
            'hotel[nameFa]' => 'هتل تست',
            'hotel[slug]' => 'test-hotel-' . strtolower($suffix),
            'hotel[stars]' => '5',
            'hotel[address]' => 'Test address',
            'hotel[latitude]' => '41.0123456',
            'hotel[longitude]' => '28.9876543',
            'hotel[website]' => 'https://example.test',
            'hotel[phone]' => '+49 221 123',
            'hotel[checkIn]' => '14:00',
            'hotel[checkOut]' => '12:00',
            'hotel[descriptionOriginal]' => 'Original description',
            'hotel[descriptionFa]' => 'توضیح فارسی',
            'hotel[active]' => '1',
            'hotel[verified]' => '1',
        ]));

        $hotel = $em->getRepository(Hotel::class)->findOneBy(['name' => 'Test Hotel ' . $suffix]);
        self::assertInstanceOf(Hotel::class, $hotel);
        self::assertResponseRedirects('/admin/catalog/hotels/' . $hotel->getId());
        self::assertSame($city->getId(), $hotel->getCity()?->getId());
        self::assertSame($district->getId(), $hotel->getDistrict()?->getId());
        self::assertTrue($hotel->isVerified());

        $crawler = $client->request('GET', sprintf('/admin/catalog/hotels/%d/images/new', $hotel->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="hotel_image[file]"]');
        self::assertSelectorExists('input[name="hotel_image[path]"]');
        $client->request('POST', sprintf('/admin/catalog/hotels/%d/images/new', $hotel->getId()), [
            'hotel_image' => [
                '_token' => $crawler->filter('input[name="hotel_image[_token]"]')->attr('value'),
                'path' => '',
                'alt' => 'Hotel',
                'altFa' => 'هتل',
                'position' => '0',
                'primary' => '1',
            ],
        ], [
            'hotel_image' => [
                'file' => self::uploadedPng(),
            ],
        ]);
        self::assertResponseRedirects('/admin/catalog/hotels/' . $hotel->getId());

        $em->clear();
        $hotelWithImage = $em->getRepository(Hotel::class)->find($hotel->getId());
        self::assertInstanceOf(Hotel::class, $hotelWithImage);
        self::assertCount(1, $hotelWithImage->getImages());
        $uploadedImage = $hotelWithImage->getImages()->first();
        self::assertNotFalse($uploadedImage);
        self::assertTrue($uploadedImage->isPrimary());
        self::assertStringStartsWith('uploads/hotels/' . $hotel->getId() . '/', $uploadedImage->getPath());
        self::assertFileExists(self::publicPath($uploadedImage->getPath()));

        $crawler = $client->request('GET', sprintf('/admin/catalog/hotels/%d/images/new', $hotel->getId()));
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('form')->form([
            'hotel_image[path]' => 'https://example.test/hotel.jpg',
            'hotel_image[alt]' => 'Hotel',
            'hotel_image[altFa]' => 'هتل',
            'hotel_image[position]' => '1',
            'hotel_image[primary]' => '1',
        ]));
        self::assertResponseRedirects('/admin/catalog/hotels/' . $hotel->getId());

        $em->clear();
        $hotelWithImage = $em->getRepository(Hotel::class)->find($hotel->getId());
        self::assertInstanceOf(Hotel::class, $hotelWithImage);
        self::assertCount(2, $hotelWithImage->getImages());
        $primaryImages = $hotelWithImage->getImages()->filter(static fn ($image): bool => $image->isPrimary());
        self::assertCount(1, $primaryImages);
        self::assertSame('https://example.test/hotel.jpg', $primaryImages->first()->getPath());

        $crawler = $client->request('GET', sprintf('/admin/catalog/hotels/%d/images/new', $hotel->getId()));
        self::assertResponseIsSuccessful();
        $client->request('POST', sprintf('/admin/catalog/hotels/%d/images/new', $hotel->getId()), [
            'hotel_image' => [
                '_token' => $crawler->filter('input[name="hotel_image[_token]"]')->attr('value'),
                'path' => '',
                'alt' => 'Invalid',
                'position' => '2',
            ],
        ], [
            'hotel_image' => [
                'file' => self::uploadedTextFile(),
            ],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'یک فایل تصویر معتبر');

        $crawler = $client->request('GET', sprintf('/admin/catalog/hotels/%d', $hotel->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('img[src*="uploads/hotels/' . $hotel->getId() . '/"]');
        $client->request('POST', sprintf('/admin/catalog/hotels/%d/images/%d/delete', $hotel->getId(), $uploadedImage->getId()), [
            '_token' => $this->imageDeleteToken($crawler, $uploadedImage->getId()),
        ]);
        self::assertResponseRedirects('/admin/catalog/hotels/' . $hotel->getId());
        self::assertFileDoesNotExist(self::publicPath($uploadedImage->getPath()));

        $crawler = $client->request('GET', sprintf('/admin/catalog/hotels/%d/edit', $hotel->getId()));
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('form')->form([
            'hotel[cityId]' => (string) $city->getId(),
            'hotel[districtId]' => '',
            'hotel[name]' => 'Updated Hotel ' . $suffix,
            'hotel[nameFa]' => 'هتل ویرایش',
            'hotel[slug]' => 'updated-hotel-' . strtolower($suffix),
            'hotel[stars]' => '4',
            'hotel[address]' => 'Updated address',
            'hotel[latitude]' => '41.1123456',
            'hotel[longitude]' => '28.1876543',
            'hotel[website]' => 'https://updated.example.test',
            'hotel[phone]' => '+49 221 456',
            'hotel[checkIn]' => '15:00',
            'hotel[checkOut]' => '11:00',
            'hotel[descriptionOriginal]' => 'Updated original',
            'hotel[descriptionFa]' => 'توضیح ویرایش',
            'hotel[active]' => '1',
            'hotel[verified]' => '1',
        ]));
        self::assertResponseRedirects('/admin/catalog/hotels/' . $hotel->getId());

        $updatedHotel = $em->getRepository(Hotel::class)->find($hotel->getId());
        self::assertInstanceOf(Hotel::class, $updatedHotel);
        self::assertSame('Updated Hotel ' . $suffix, $updatedHotel->getName());
        self::assertNull($updatedHotel->getDistrict());

        $crawler = $client->request('GET', '/admin/catalog/hotels/', ['q' => 'Updated Hotel ' . $suffix]);
        $client->request('POST', sprintf('/admin/catalog/hotels/%d/delete', $updatedHotel->getId()), [
            '_token' => $this->deleteToken($crawler, $updatedHotel->getId()),
        ]);
        self::assertResponseRedirects('/admin/catalog/hotels/');
        $deactivatedHotel = $em->getRepository(Hotel::class)->find($updatedHotel->getId());
        self::assertInstanceOf(Hotel::class, $deactivatedHotel);
        self::assertFalse($deactivatedHotel->isActive());
    }

    public function testHotelRejectsDistrictFromDifferentCity(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());
        $suffix = self::uniqueSuffix();
        [$city] = $this->persistGeography($suffix);
        [, $otherDistrict] = $this->persistGeography($suffix . 'B');

        $crawler = $client->request('GET', '/admin/catalog/hotels/new');
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('form')->form([
            'hotel[cityId]' => (string) $city->getId(),
            'hotel[districtId]' => (string) $otherDistrict->getId(),
            'hotel[name]' => 'Mismatch Hotel ' . $suffix,
            'hotel[slug]' => 'mismatch-hotel-' . strtolower($suffix),
            'hotel[active]' => '1',
        ]));

        self::assertResponseIsSuccessful();
        self::assertNull($this->entityManager()->getRepository(Hotel::class)->findOneBy(['name' => 'Mismatch Hotel ' . $suffix]));
    }

    public function testHotelListFiltersAndPagination(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());
        $suffix = self::uniqueSuffix();
        [$city, $district] = $this->persistGeography($suffix);
        $em = $this->entityManager();

        $target = (new Hotel())
            ->setCity($city)
            ->setDistrict($district)
            ->setName('Filter Hotel ' . $suffix)
            ->setNameFa('هتل فیلتر ' . $suffix)
            ->setSlug('filter-hotel-' . strtolower($suffix))
            ->setStars(5)
            ->setVerified(true);
        $inactive = (new Hotel())
            ->setCity($city)
            ->setName('Inactive Hotel ' . $suffix)
            ->setSlug('inactive-hotel-' . strtolower($suffix))
            ->setActive(false);
        $em->persist($target);
        $em->persist($inactive);

        for ($i = 1; $i <= 30; $i++) {
            $em->persist((new Hotel())
                ->setCity($city)
                ->setName(sprintf('Paged Hotel %s %02d', $suffix, $i))
                ->setSlug(sprintf('paged-hotel-%s-%02d', strtolower($suffix), $i)));
        }
        $em->flush();

        $client->request('GET', '/admin/catalog/hotels/', [
            'name' => 'Filter Hotel ' . $suffix,
            'nameFa' => 'هتل فیلتر',
            'city' => $city->getId(),
            'district' => $district->getId(),
            'stars' => '5',
            'active' => '1',
            'verified' => '1',
            'pageSize' => 25,
        ]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="name"]');
        self::assertSelectorExists('input[name="nameFa"]');
        self::assertSelectorExists('input[name="city"]');
        self::assertSelectorExists('input[name="district"]');
        self::assertSelectorExists('select[name="stars"]');
        self::assertSelectorExists('select[name="active"]');
        self::assertSelectorExists('select[name="verified"]');
        self::assertSelectorTextContains('body', 'Filter Hotel ' . $suffix);
        self::assertSelectorTextNotContains('body', 'Inactive Hotel ' . $suffix);

        $crawler = $client->request('GET', '/admin/catalog/hotels/', [
            'name' => 'Paged Hotel ' . $suffix,
            'pageSize' => 25,
            'page' => 2,
        ]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href*="page=1"]');
        self::assertSelectorExists('a[href*="page=2"]');
        self::assertSelectorExists('input[name="page"]');
        self::assertSelectorExists('select[name="pageSize"]');
        self::assertSame('Paged Hotel ' . $suffix, $crawler->filter('input[name="name"]')->attr('value'));

        $crawler = $client->request('GET', '/admin/catalog/hotels/', [
            'name' => 'Paged Hotel ' . $suffix,
            'pageSize' => 25,
            'page' => 99,
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('2', $crawler->filter('input[name="page"]')->attr('value'));

        $client->request('GET', '/admin/catalog/hotels/', [
            'name' => 'Paged Hotel ' . $suffix,
            'pageSize' => 999999,
        ]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="pageSize"] option[value="100"][selected]');
    }

    public function testAmenityCrudAndFilters(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());
        $suffix = self::uniqueSuffix();

        $crawler = $client->request('GET', '/admin/catalog/hotel-amenities/new');
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('form')->form([
            'hotel_amenity[name]' => 'Amenity ' . $suffix,
            'hotel_amenity[nameFa]' => 'امکان تست',
            'hotel_amenity[code]' => 'Amenity ' . $suffix,
            'hotel_amenity[active]' => '1',
        ]));
        self::assertResponseRedirects('/admin/catalog/hotel-amenities/');

        $amenity = $this->entityManager()->getRepository(HotelAmenity::class)->findOneBy(['name' => 'Amenity ' . $suffix]);
        self::assertInstanceOf(HotelAmenity::class, $amenity);
        self::assertSame('amenity-' . strtolower($suffix), $amenity->getCode());

        $client->request('GET', '/admin/catalog/hotel-amenities/', [
            'name' => 'Amenity ' . $suffix,
            'nameFa' => 'امکان',
            'code' => 'amenity-' . strtolower($suffix),
            'active' => '1',
            'pageSize' => 25,
        ]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Amenity ' . $suffix);
        self::assertSelectorExists('input[name="code"]');
        self::assertSelectorExists('select[name="active"]');
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
            ->setEmail('phase3-admin-' . strtolower(self::uniqueSuffix()) . '@example.test')
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

    /**
     * @return array{0: City, 1: District}
     */
    private function persistGeography(string $suffix): array
    {
        $country = (new Country())
            ->setName('Hotel Country ' . $suffix);
        $state = (new State())
            ->setCountry($country)
            ->setName('Hotel State ' . $suffix)
            ->setCode('H' . substr($suffix, 0, 2))
            ->setSlug('hotel-state-' . strtolower($suffix));
        $city = (new City())
            ->setCountry($country)
            ->setState($state)
            ->setName('Hotel City ' . $suffix)
            ->setNameFa('شهر هتل ' . $suffix)
            ->setSlug('hotel-city-' . strtolower($suffix));
        $district = (new District())
            ->setCity($city)
            ->setName('Hotel District ' . $suffix)
            ->setNameFa('محله هتل ' . $suffix)
            ->setSlug('hotel-district-' . strtolower($suffix));

        $em = $this->entityManager();
        foreach ([$country, $state, $city, $district] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        return [$city, $district];
    }

    private function deleteToken(Crawler $crawler, int $entityId): string
    {
        return $crawler
            ->filter(sprintf('form[action$="/%d/delete"] input[name="_token"]', $entityId))
            ->attr('value') ?? '';
    }

    private function imageDeleteToken(Crawler $crawler, int $imageId): string
    {
        return $crawler
            ->filter(sprintf('form[action$="/images/%d/delete"] input[name="_token"]', $imageId))
            ->attr('value') ?? '';
    }

    private static function uniqueSuffix(): string
    {
        $letters = '';
        for ($index = 0; $index < 6; $index++) {
            $letters .= chr(random_int(65, 90));
        }

        return $letters;
    }

    private static function uploadedPng(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'hotel-image-');
        self::assertIsString($path);
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        return new UploadedFile($path, 'hotel.png', 'image/png', null, true);
    }

    private static function uploadedTextFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'hotel-image-invalid-');
        self::assertIsString($path);
        file_put_contents($path, 'not an image');

        return new UploadedFile($path, 'hotel.txt', 'text/plain', null, true);
    }

    private static function publicPath(string $relativePath): string
    {
        return dirname(__DIR__, 3) . '/public/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    }
}
