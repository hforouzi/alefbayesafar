<?php

namespace App\Tests\Modules\Hotel;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Entity\District;
use App\Modules\Destination\Entity\State;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelSourceReference;
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
        self::assertSame('هتل آرتس', $hotel->getNameFa());
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

    private function candidate(?string $externalId, string $suffix): HotelCandidate
    {
        return new HotelCandidate(
            sourceIdentifier: 'booking',
            sourceName: 'Booking',
            providerCode: 'firecrawl',
            externalId: $externalId,
            sourceUrl: 'https://booking.com/hotel/tr/arts-' . strtolower($suffix) . '.html',
            sourceTitle: 'Arts Hotel Istanbul ' . $suffix,
            name: 'Arts Hotel Istanbul ' . $suffix,
            nameFa: 'هتل آرتس',
            address: 'Taksim',
            countryName: 'Turkey',
            cityName: 'Istanbul',
            districtName: 'Taksim',
            stars: 5,
            latitude: '41.0123456',
            longitude: '28.9876543',
            website: 'booking.com/hotel/tr/arts-' . strtolower($suffix) . '.html',
            phone: '+90 212 000',
            descriptionOriginal: 'Central hotel.',
            descriptionFa: null,
            rawData: ['source' => 'test'],
        );
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
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
