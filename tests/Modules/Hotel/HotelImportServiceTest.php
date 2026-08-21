<?php

namespace App\Tests\Modules\Hotel;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Entity\District;
use App\Modules\Destination\Entity\State;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelAmenity;
use App\Modules\Hotel\Entity\HotelImage;
use App\Modules\Hotel\Entity\HotelSourceReference;
use App\Modules\Hotel\Service\HotelAmenityCatalog;
use App\Modules\Hotel\Service\HotelImportService;
use App\Modules\Hotel\ValueObject\HotelCandidate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class HotelImportServiceTest extends KernelTestCase
{
    public function testImportCreatesCanonicalHotelAndSourceReferenceWithSearchSourceIdentifier(): void
    {
        self::bootKernel();
        $em = $this->entityManager();
        $suffix = self::uniqueSuffix();
        [$city, $district] = $this->persistGeography('IMP' . $suffix);
        $candidate = $this->candidate('booking-' . $suffix, $suffix);

        $result = self::getContainer()->get(HotelImportService::class)->import($candidate, $city, $district);

        self::assertTrue($result['created']);
        $hotel = $result['hotel'];
        self::assertInstanceOf(Hotel::class, $hotel);
        self::assertSame('Arts Hotel Istanbul ' . $suffix, $hotel->getName());
        self::assertSame($this->sourcePersianHotelName(), $hotel->getNameFa());
        self::assertSame($city->getId(), $hotel->getCity()?->getId());
        self::assertSame($district->getId(), $hotel->getDistrict()?->getId());
        self::assertSame('https://booking.com/hotel/tr/arts-' . strtolower($suffix) . '.html', $hotel->getWebsite());

        $reference = $em->getRepository(HotelSourceReference::class)->findOneBy(['source' => 'booking', 'externalId' => 'booking-' . $suffix]);
        self::assertInstanceOf(HotelSourceReference::class, $reference);
        self::assertSame($hotel->getId(), $reference->getHotel()?->getId());
        self::assertSame('firecrawl', $reference->getMetadata()['provider'] ?? null);
    }

    public function testSecondImportMatchesExistingSourceReferenceAndKeepsCanonicalId(): void
    {
        self::bootKernel();
        $suffix = self::uniqueSuffix();
        [$city, $district] = $this->persistGeography('IDEM' . $suffix);
        $service = self::getContainer()->get(HotelImportService::class);

        $first = $service->import($this->candidate('same-id-' . $suffix, $suffix), $city, $district);
        $second = $service->import($this->candidate('same-id-' . $suffix, $suffix), $city, $district);

        self::assertTrue($first['created']);
        self::assertFalse($second['created']);
        self::assertSame($first['hotel']->getId(), $second['hotel']->getId());
        self::assertSame('source_external_id', $second['matchedBy']);
    }

    public function testImportMatchesExistingHotelByCitySlug(): void
    {
        self::bootKernel();
        $em = $this->entityManager();
        $suffix = self::uniqueSuffix();
        [$city] = $this->persistGeography('SLUG' . $suffix);
        $existing = (new Hotel())
            ->setCity($city)
            ->setName('Arts Hotel Istanbul ' . $suffix)
            ->setSlug('arts-hotel-istanbul-' . strtolower($suffix));
        $em->persist($existing);
        $em->flush();

        $result = self::getContainer()->get(HotelImportService::class)->import($this->candidate(null, $suffix), $city);

        self::assertFalse($result['created']);
        self::assertSame($existing->getId(), $result['hotel']->getId());
        self::assertSame('city_slug', $result['matchedBy']);
    }

    public function testImportRejectsDistrictFromDifferentCity(): void
    {
        self::bootKernel();
        [$city] = $this->persistGeography('A');
        [, $district] = $this->persistGeography('B');

        $this->expectException(\InvalidArgumentException::class);
        self::getContainer()->get(HotelImportService::class)->import($this->candidate('bad-' . self::uniqueSuffix(), self::uniqueSuffix()), $city, $district);
    }

    public function testImportEnrichesNewHotelWithDetailsImagesAmenitiesAndSourcePersianValues(): void
    {
        self::bootKernel();
        $this->seedAmenities();
        $em = $this->entityManager();
        $suffix = self::uniqueSuffix();
        [$city] = $this->persistGeography('ENR' . $suffix);
        $candidate = $this->candidate('enrich-' . $suffix, $suffix, [
            'nameFa' => $this->sourcePersianHotelName(),
            'address' => 'Harbiye Mahallesi, Istanbul',
            'stars' => 5,
            'latitude' => '41.047',
            'longitude' => '28.987',
            'website' => 'https://www.booking.com/hotel/tr/arts-' . strtolower($suffix) . '.html',
            'phone' => '+90 212 123',
            'descriptionOriginal' => 'Free WiFi, swimming pool, spa, parking, breakfast and restaurant.',
            'descriptionFa' => $this->sourcePersianDescription(),
            'images' => [
                ['url' => 'https://img.example.test/a.jpg', 'alt' => 'A'],
                ['url' => 'https://img.example.test/a.jpg', 'alt' => 'Duplicate'],
                ['url' => 'https://img.example.test/b.webp', 'alt' => 'B'],
            ],
            'rawData' => [
                'markdown' => str_repeat('Large markdown ', 100),
                'description' => 'Free WiFi and restaurant.',
            ],
        ]);

        $result = self::getContainer()->get(HotelImportService::class)->import($candidate, $city);

        self::assertTrue($result['created']);
        self::assertSame(2, $result['imagesAdded']);
        self::assertSame(6, $result['amenitiesAdded']);
        $hotelId = $result['hotel']->getId();
        $em->clear();

        $stored = $em->getRepository(Hotel::class)->find($hotelId);
        self::assertInstanceOf(Hotel::class, $stored);
        self::assertSame($this->sourcePersianHotelName(), $stored->getNameFa());
        self::assertSame('Harbiye Mahallesi, Istanbul', $stored->getAddress());
        self::assertSame(5, $stored->getStars());
        self::assertSame('41.0470000', $stored->getLatitude());
        self::assertSame('28.9870000', $stored->getLongitude());
        self::assertSame('https://www.booking.com/hotel/tr/arts-' . strtolower($suffix) . '.html', $stored->getWebsite());
        self::assertSame('+90 212 123', $stored->getPhone());
        self::assertSame('Free WiFi, swimming pool, spa, parking, breakfast and restaurant.', $stored->getDescriptionOriginal());
        self::assertSame($this->sourcePersianDescription(), $stored->getDescriptionFa());
        self::assertCount(2, $stored->getImages());
        self::assertTrue($stored->getImages()->first()->isPrimary());
        self::assertCount(6, $stored->getAmenities());

        $reference = $stored->getSourceReferences()->first();
        self::assertInstanceOf(HotelSourceReference::class, $reference);
        self::assertArrayNotHasKey('markdown', $reference->getMetadata()['rawData'] ?? []);
    }

    public function testExistingCuratedHotelDataIsProtectedWhileMissingValuesImagesAndAmenitiesAreAdded(): void
    {
        self::bootKernel();
        $this->seedAmenities();
        $em = $this->entityManager();
        $suffix = self::uniqueSuffix();
        [$city] = $this->persistGeography('CUR' . $suffix);
        $hotel = (new Hotel())
            ->setCity($city)
            ->setName('Arts Hotel Istanbul ' . $suffix)
            ->setSlug('arts-hotel-istanbul-' . strtolower($suffix))
            ->setAddress('Curated address')
            ->setWebsite('https://curated.example.test')
            ->setDescriptionOriginal('Curated description');
        $hotel->addImage((new HotelImage())->setPath('https://img.example.test/existing.jpg')->setPosition(0)->setPrimary(true));
        $em->persist($hotel);
        $em->flush();

        $candidate = $this->candidate(null, $suffix, [
            'address' => 'Source address',
            'website' => 'https://source.example.test',
            'phone' => '+90 555',
            'descriptionOriginal' => 'Source description with Free WiFi and bar.',
            'images' => [
                ['url' => 'https://img.example.test/existing.jpg'],
                ['url' => 'https://img.example.test/new.jpg'],
            ],
        ]);

        $result = self::getContainer()->get(HotelImportService::class)->import($candidate, $city);

        self::assertFalse($result['created']);
        self::assertSame(1, $result['imagesAdded']);
        self::assertSame(2, $result['amenitiesAdded']);
        $em->clear();

        $stored = $em->getRepository(Hotel::class)->find($hotel->getId());
        self::assertInstanceOf(Hotel::class, $stored);
        self::assertSame('Curated address', $stored->getAddress());
        self::assertSame('https://curated.example.test', $stored->getWebsite());
        self::assertSame('Curated description', $stored->getDescriptionOriginal());
        self::assertSame('+90 555', $stored->getPhone());
        self::assertCount(2, $stored->getImages());
        self::assertCount(2, $stored->getAmenities());

        $second = self::getContainer()->get(HotelImportService::class)->import($candidate, $city);
        self::assertSame(0, $second['imagesAdded']);
        self::assertSame(0, $second['amenitiesAdded']);
    }

    public function testMissingPersianSourceValuesAreNotGenerated(): void
    {
        self::bootKernel();
        $suffix = self::uniqueSuffix();
        [$city] = $this->persistGeography('FAR' . $suffix);

        $hotel = self::getContainer()->get(HotelImportService::class)->import(
            $this->candidate('no-fa-' . $suffix, $suffix, ['nameFa' => null, 'descriptionFa' => null]),
            $city,
        )['hotel'];

        self::assertNull($hotel->getNameFa());
        self::assertNull($hotel->getDescriptionFa());
    }

    public function testImportLimitsRemoteImagesToEight(): void
    {
        self::bootKernel();
        $suffix = self::uniqueSuffix();
        [$city] = $this->persistGeography('IMG' . $suffix);
        $images = [];
        for ($index = 1; $index <= 10; ++$index) {
            $images[] = ['url' => sprintf('https://img.example.test/%02d.jpg', $index)];
        }

        $result = self::getContainer()->get(HotelImportService::class)->import(
            $this->candidate('images-' . $suffix, $suffix, ['images' => $images]),
            $city,
        );

        self::assertSame(8, $result['imagesAdded']);
        self::assertCount(8, $result['hotel']->getImages());
    }

    /**
     * @return array{0: City, 1: District}
     */
    private function persistGeography(string $suffix): array
    {
        $country = (new Country())->setName('Import Country ' . $suffix);
        $state = (new State())->setCountry($country)->setName('Import State ' . $suffix)->setCode('I' . $suffix)->setSlug('import-state-' . strtolower($suffix));
        $city = (new City())->setCountry($country)->setState($state)->setName('Istanbul ' . $suffix)->setSlug('istanbul-' . strtolower($suffix));
        $district = (new District())->setCity($city)->setName('Taksim ' . $suffix)->setSlug('taksim-' . strtolower($suffix));
        $em = $this->entityManager();
        foreach ([$country, $state, $city, $district] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        return [$city, $district];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function candidate(?string $externalId, string $suffix, array $overrides = []): HotelCandidate
    {
        return new HotelCandidate(
            sourceIdentifier: 'booking',
            sourceName: 'Booking',
            providerCode: 'firecrawl',
            externalId: $overrides['externalId'] ?? $externalId,
            sourceUrl: $overrides['sourceUrl'] ?? 'https://booking.com/hotel/tr/arts-' . strtolower($suffix) . '.html',
            sourceTitle: $overrides['sourceTitle'] ?? 'Arts Hotel Istanbul ' . $suffix,
            name: $overrides['name'] ?? 'Arts Hotel Istanbul ' . $suffix,
            nameFa: \array_key_exists('nameFa', $overrides) ? $overrides['nameFa'] : $this->sourcePersianHotelName(),
            address: $overrides['address'] ?? 'Taksim',
            countryName: 'Turkey',
            cityName: 'Istanbul',
            districtName: 'Taksim',
            stars: $overrides['stars'] ?? 5,
            latitude: $overrides['latitude'] ?? '41.0123456',
            longitude: $overrides['longitude'] ?? '28.9876543',
            website: $overrides['website'] ?? 'booking.com/hotel/tr/arts-' . strtolower($suffix) . '.html',
            phone: $overrides['phone'] ?? '+90 212 000',
            descriptionOriginal: $overrides['descriptionOriginal'] ?? 'Central hotel.',
            descriptionFa: \array_key_exists('descriptionFa', $overrides) ? $overrides['descriptionFa'] : null,
            images: $overrides['images'] ?? [],
            rawData: $overrides['rawData'] ?? ['source' => 'test'],
        );
    }

    private function sourcePersianHotelName(): string
    {
        return "\u{0647}\u{062A}\u{0644} \u{0622}\u{0631}\u{062A}\u{0633}";
    }

    private function sourcePersianDescription(): string
    {
        return "\u{062A}\u{0648}\u{0636}\u{06CC}\u{062D} \u{0641}\u{0627}\u{0631}\u{0633}\u{06CC} \u{0645}\u{0646}\u{0628}\u{0639}";
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function seedAmenities(): void
    {
        $em = $this->entityManager();
        self::getContainer()->get(HotelAmenityCatalog::class)->seed($em->getRepository(HotelAmenity::class), $em);
        $em->flush();
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
