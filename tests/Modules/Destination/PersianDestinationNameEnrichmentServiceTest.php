<?php

namespace App\Tests\Modules\Destination;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Entity\DestinationSourceReference;
use App\Modules\Destination\Entity\State;
use App\Modules\Destination\Service\PersianDestinationNameEnrichmentService;
use App\Modules\Destination\ValueObject\DestinationEntityType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;

class PersianDestinationNameEnrichmentServiceTest extends KernelTestCase
{
    public function testGeoNamesAlternateNamesFillPersianNamesWithoutOverwritingCuratedValues(): void
    {
        self::bootKernel();
        $em = $this->entityManager();
        $suffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $iso2 = $this->unusedIso2();
        $cacheDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'alefbayesafar-persian-enrichment-' . strtolower($suffix);
        $filesystem = new Filesystem();
        $filesystem->mkdir($cacheDirectory . DIRECTORY_SEPARATOR . 'alternatenames');

        $this->writeAlternateNamesZip($cacheDirectory, $iso2, [
            ['1', 'geo-country-' . $suffix, 'fa', 'کشور فارسی ' . $suffix],
            ['2', 'geo-state-' . $suffix, 'fa', 'استان فارسی ' . $suffix],
            ['3', 'geo-city-' . $suffix, 'fa', 'شهر فارسی ' . $suffix],
        ]);

        $country = (new Country())
            ->setName('Persian Enrichment Country ' . $suffix)
            ->setIso2($iso2);
        $state = (new State())
            ->setCountry($country)
            ->setName('Persian Enrichment State ' . $suffix)
            ->setNameFa('نام curated ' . $suffix)
            ->setCode($suffix)
            ->setSlug('persian-enrichment-state-' . strtolower($suffix));
        $city = (new City())
            ->setCountry($country)
            ->setState($state)
            ->setName('Persian Enrichment City ' . $suffix)
            ->setSlug('persian-enrichment-city-' . strtolower($suffix));
        $airport = (new Airport())
            ->setCity($city)
            ->setName('Persian Enrichment Airport ' . $suffix)
            ->setIataCode($this->unusedIataCode());

        foreach ([$country, $state, $city, $airport] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $em->persist($this->reference(DestinationEntityType::COUNTRY, (int) $country->getId(), 'geo-country-' . $suffix));
        $em->persist($this->reference(DestinationEntityType::STATE, (int) $state->getId(), 'geo-state-' . $suffix));
        $em->persist($this->reference(DestinationEntityType::CITY, (int) $city->getId(), 'geo-city-' . $suffix));
        $em->flush();

        $service = new PersianDestinationNameEnrichmentService(new MockHttpClient(), $em, $cacheDirectory, $filesystem);
        $result = $service->enrich($iso2);

        self::assertSame(1, $result['countries']);
        self::assertSame(0, $result['states']);
        self::assertSame(1, $result['cities']);
        self::assertSame(0, $result['airports']);
        self::assertGreaterThanOrEqual(1, $result['skippedCurated']);

        $em->refresh($country);
        $em->refresh($state);
        $em->refresh($city);
        $em->refresh($airport);

        self::assertSame('کشور فارسی ' . $suffix, $country->getNameFa());
        self::assertSame('نام curated ' . $suffix, $state->getNameFa());
        self::assertSame('شهر فارسی ' . $suffix, $city->getNameFa());
        self::assertNull($airport->getNameFa());

        $filesystem->remove($cacheDirectory);
    }

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $rows
     */
    private function writeAlternateNamesZip(string $cacheDirectory, string $iso2, array $rows): void
    {
        $zipPath = $cacheDirectory . DIRECTORY_SEPARATOR . 'alternatenames' . DIRECTORY_SEPARATOR . $iso2 . '.zip';
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($zipPath, \ZipArchive::CREATE));

        $content = '';
        foreach ($rows as $row) {
            $content .= implode("\t", $row) . "\n";
        }

        self::assertTrue($zip->addFromString($iso2 . '.txt', $content));
        self::assertTrue($zip->close());
    }

    private function reference(string $type, int $canonicalId, string $externalId): DestinationSourceReference
    {
        return (new DestinationSourceReference())
            ->setSource('geonames')
            ->setCanonicalType($type)
            ->setCanonicalId($canonicalId)
            ->setExternalId($externalId);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function unusedIso2(): string
    {
        do {
            $iso2 = chr(random_int(65, 90)) . chr(random_int(65, 90));
        } while ($this->entityManager()->getRepository(Country::class)->count(['iso2' => $iso2]) > 0);

        return $iso2;
    }

    private function unusedIataCode(): string
    {
        do {
            $iata = chr(random_int(65, 90)) . chr(random_int(65, 90)) . chr(random_int(65, 90));
        } while ($this->entityManager()->getRepository(Airport::class)->count(['iataCode' => $iata]) > 0);

        return $iata;
    }
}
