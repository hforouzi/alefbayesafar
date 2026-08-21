<?php

namespace App\Tests\Modules\Hotel;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelImage;
use App\Modules\Hotel\Service\HotelImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class HotelImageStorageTest extends KernelTestCase
{
    public function testStoreUsesDetectedMimeExtensionAndSafeUploadDirectory(): void
    {
        self::bootKernel();
        $hotel = $this->persistHotel('Storage Hotel ' . self::uniqueSuffix());
        $storage = self::getContainer()->get(HotelImageStorage::class);
        self::assertInstanceOf(HotelImageStorage::class, $storage);

        $path = $storage->store($hotel, self::uploadedPng('malicious.php'));

        self::assertStringStartsWith('uploads/hotels/' . $hotel->getId() . '/', $path);
        self::assertStringEndsWith('.png', $path);
        self::assertStringNotContainsString('..', $path);
        self::assertFileExists(self::publicPath($path));

        (new Filesystem())->remove(self::publicPath('uploads/hotels/' . $hotel->getId()));
    }

    public function testDeleteIgnoresTraversalAndExternalUrlPaths(): void
    {
        self::bootKernel();
        $hotel = $this->persistHotel('Traversal Hotel ' . self::uniqueSuffix());
        $storage = self::getContainer()->get(HotelImageStorage::class);
        self::assertInstanceOf(HotelImageStorage::class, $storage);
        $filesystem = new Filesystem();
        $hotelUploadDirectory = self::publicPath('uploads/hotels/' . $hotel->getId());
        $outsidePath = self::publicPath('uploads/should-not-delete-' . self::uniqueSuffix() . '.txt');

        $filesystem->mkdir($hotelUploadDirectory);
        file_put_contents($outsidePath, 'keep');

        $maliciousImage = (new HotelImage())
            ->setHotel($hotel)
            ->setPath('uploads/hotels/' . $hotel->getId() . '/../../' . basename($outsidePath));

        self::assertFalse($storage->isOwnedLocalFile($maliciousImage));
        $storage->removeIfOwnedLocalFile($maliciousImage);
        self::assertFileExists($outsidePath);

        $externalImage = (new HotelImage())
            ->setHotel($hotel)
            ->setPath('https://example.test/uploads/hotels/' . $hotel->getId() . '/image.jpg');

        self::assertFalse($storage->isOwnedLocalFile($externalImage));
        $storage->removeIfOwnedLocalFile($externalImage);
        self::assertFileExists($outsidePath);

        $filesystem->remove([$hotelUploadDirectory, $outsidePath]);
    }

    private function persistHotel(string $name): Hotel
    {
        $country = (new Country())->setName('Storage Country ' . self::uniqueSuffix());
        $city = (new City())
            ->setCountry($country)
            ->setName('Storage City ' . self::uniqueSuffix())
            ->setSlug('storage-city-' . strtolower(self::uniqueSuffix()));
        $hotel = (new Hotel())
            ->setCity($city)
            ->setName($name)
            ->setSlug(Hotel::normalizeSlug($name));

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->persist($country);
        $em->persist($city);
        $em->persist($hotel);
        $em->flush();

        return $hotel;
    }

    private static function uploadedPng(string $originalName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'hotel-storage-');
        self::assertIsString($path);
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        return new UploadedFile($path, $originalName, 'image/png', null, true);
    }

    private static function publicPath(string $relativePath): string
    {
        return dirname(__DIR__, 3) . '/public/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    private static function uniqueSuffix(): string
    {
        return strtolower(bin2hex(random_bytes(3)));
    }
}
