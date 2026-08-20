<?php

namespace App\Tests\Modules\Destination;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Entity\DestinationImportRun;
use App\Modules\Destination\Entity\DestinationSourceReference;
use App\Modules\Destination\Entity\District;
use App\Modules\Destination\Entity\State;
use App\Modules\Destination\Provider\DestinationProviderInterface;
use App\Modules\Destination\Provider\DestinationProviderRegistry;
use App\Modules\Destination\Repository\AirportRepository;
use App\Modules\Destination\Repository\CityRepository;
use App\Modules\Destination\Repository\CountryRepository;
use App\Modules\Destination\Repository\DestinationSourceReferenceRepository;
use App\Modules\Destination\Repository\DistrictRepository;
use App\Modules\Destination\Repository\StateRepository;
use App\Modules\Destination\Service\DestinationImportService;
use App\Modules\Destination\Service\DestinationNormalizer;
use App\Modules\Destination\ValueObject\DestinationCandidate;
use App\Modules\Destination\ValueObject\DestinationEntityType;
use App\Modules\Destination\ValueObject\DestinationImportRequest;
use App\Modules\Destination\ValueObject\DestinationProviderResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DestinationImportServiceTest extends KernelTestCase
{
    public function testSameSourceImportedTwiceDoesNotDuplicateCanonicalRecords(): void
    {
        self::bootKernel();
        $suffix = self::uniqueSuffix();
        $service = $this->serviceWithProviders([
            new StaticDestinationProvider('booking', 'Booking', self::istanbulCandidates('booking', $suffix)),
        ]);

        $request = self::cityRequest($suffix, ['booking']);
        $first = $service->import($request);
        $second = $service->import($request);

        self::assertSame(4, $first->created);
        self::assertSame(0, $second->created);
        self::assertGreaterThanOrEqual(4, $second->unchanged);
        self::assertSame(1, $this->countBy(Country::class, ['name' => 'Turkey ' . $suffix]));
        $city = $this->city($suffix);
        self::assertSame(1, $this->countBy(City::class, ['name' => 'Istanbul ' . $suffix]));
        self::assertSame(1, $this->countBy(District::class, ['city' => $city]));
        self::assertSame(1, $this->countBy(Airport::class, ['iataCode' => substr($suffix, 0, 3)]));
        foreach (['country', 'city', 'taksim', 'airport'] as $externalKey) {
            self::assertSame(1, $this->countBy(DestinationSourceReference::class, [
                'source' => 'booking',
                'externalId' => 'booking-' . $externalKey . '-' . $suffix,
            ]));
        }
    }

    public function testBookingAndTripadvisorTaksimMergeIntoOneDistrictWhenMatchIsDeterministic(): void
    {
        self::bootKernel();
        $suffix = self::uniqueSuffix();
        $providers = [
            new StaticDestinationProvider('booking', 'Booking', [
                self::countryCandidate('booking', $suffix),
                self::cityCandidate('booking', $suffix),
                self::districtCandidate('booking', $suffix, 'Taksim', 'b-taksim-' . $suffix),
            ]),
            new StaticDestinationProvider('tripadvisor', 'Tripadvisor', [
                self::countryCandidate('tripadvisor', $suffix),
                self::cityCandidate('tripadvisor', $suffix),
                self::districtCandidate('tripadvisor', $suffix, 'Taksim Square Area', 'ta-taksim-' . $suffix),
            ]),
        ];

        $summary = $this->serviceWithProviders($providers)->import(self::cityRequest($suffix, ['booking', 'tripadvisor']));

        self::assertSame(1, $this->countBy(Country::class, ['name' => 'Turkey ' . $suffix]));
        self::assertSame(1, $this->countBy(City::class, ['name' => 'Istanbul ' . $suffix]));
        self::assertSame(1, $this->countBy(District::class, ['city' => $this->city($suffix)]));
        self::assertGreaterThanOrEqual(1, $summary->updated + $summary->unchanged);
    }

    public function testProviderFailureIsReportedInsteadOfSuccessfulEmptyResult(): void
    {
        self::bootKernel();
        $suffix = self::uniqueSuffix();
        $summary = $this->serviceWithProviders([
            new FailingDestinationProvider('booking', 'Booking', 'HTTP 403 from source.'),
        ])->import(self::cityRequest($suffix, ['booking']));

        self::assertSame(0, $summary->found);
        self::assertSame(1, $summary->failed);
        self::assertNotSame([], $summary->errors);

        $run = $summary->run;
        self::assertSame(DestinationImportRun::STATUS_FAILED, $run->getStatus());
    }

    public function testCountryMatchesByIsoWithoutDuplicate(): void
    {
        self::bootKernel();
        [$iso2, $iso3] = $this->unusedIsoCodes();
        $existing = (new Country())
            ->setName('Existing ISO Country ' . $iso3)
            ->setIso2($iso2)
            ->setIso3($iso3);
        $this->entityManager()->persist($existing);
        $this->entityManager()->flush();

        $summary = $this->serviceWithProviders([
            new StaticDestinationProvider('booking', 'Booking', [
                new DestinationCandidate(
                    provider: 'booking',
                    type: DestinationEntityType::COUNTRY,
                    name: 'Provider ISO Country ' . $iso3,
                    externalId: 'booking-country-iso-' . $iso3,
                    iso2: $iso2,
                    iso3: $iso3,
                    rawData: ['kind' => 'country'],
                ),
            ]),
        ])->import(new DestinationImportRequest(
            targetType: DestinationEntityType::COUNTRY,
            countryName: 'Provider ISO Country ' . $iso3,
            cityName: null,
            providerCodes: ['booking'],
            refresh: true,
        ));

        self::assertSame(0, $summary->created);
        self::assertSame(1, $this->countBy(Country::class, ['iso2' => $iso2]));
        self::assertSame(1, $this->countBy(DestinationSourceReference::class, [
            'source' => 'booking',
            'canonicalType' => DestinationEntityType::COUNTRY,
            'canonicalId' => $existing->getId(),
        ]));
    }

    public function testGeoNamesStateAndCityImportIsIdempotentAndKeepsHierarchy(): void
    {
        self::bootKernel();
        $suffix = self::uniqueSuffix();
        [$iso2, $iso3] = $this->unusedIsoCodes();
        $service = $this->serviceWithProviders([
            new StaticDestinationProvider('geonames', 'GeoNames', [
                new DestinationCandidate(
                    provider: 'geonames',
                    type: DestinationEntityType::COUNTRY,
                    name: 'Geo Country ' . $suffix,
                    externalId: 'geo-country-' . $suffix,
                    iso2: $iso2,
                    iso3: $iso3,
                    rawData: ['source' => 'countryInfo.txt'],
                ),
                new DestinationCandidate(
                    provider: 'geonames',
                    type: DestinationEntityType::STATE,
                    name: 'Geo State ' . $suffix,
                    countryName: $iso2,
                    nameFa: 'استان تست',
                    externalId: 'geo-state-' . $suffix,
                    admin1Code: '34',
                    rawData: ['source' => 'admin1CodesASCII.txt'],
                ),
                new DestinationCandidate(
                    provider: 'geonames',
                    type: DestinationEntityType::CITY,
                    name: 'Geo City ' . $suffix,
                    countryName: $iso2,
                    nameFa: 'شهر تست',
                    externalId: 'geo-city-' . $suffix,
                    admin1Code: '34',
                    rawData: ['source' => 'cities15000'],
                ),
            ]),
        ]);

        $request = new DestinationImportRequest(DestinationEntityType::CITY, $iso2, null, ['geonames'], true);
        $first = $service->import($request);
        $second = $service->import($request);
        $city = $this->entityManager()->getRepository(City::class)->findOneBy(['name' => 'Geo City ' . $suffix]);

        self::assertSame(3, $first->created);
        self::assertSame(0, $second->created);
        self::assertGreaterThanOrEqual(3, $second->unchanged);
        self::assertInstanceOf(City::class, $city);
        self::assertInstanceOf(State::class, $city->getState());
        self::assertSame('34', $city->getState()->getCode());
        self::assertSame('شهر تست', $city->getNameFa());
        self::assertSame(1, $this->countBy(DestinationSourceReference::class, [
            'source' => 'geonames',
            'canonicalType' => DestinationEntityType::STATE,
            'externalId' => 'geo-state-' . $suffix,
        ]));
    }

    public function testGeoNamesCityImportMapsExistingCityToStateWithoutChangingCityId(): void
    {
        self::bootKernel();
        $suffix = self::uniqueSuffix();
        [$iso2, $iso3] = $this->unusedIsoCodes();
        $country = (new Country())
            ->setName('Existing Geo Country ' . $suffix)
            ->setIso2($iso2)
            ->setIso3($iso3);
        $state = (new State())
            ->setCountry($country)
            ->setName('Existing Geo State ' . $suffix)
            ->setCode('34')
            ->setSlug('existing-geo-state-' . strtolower($suffix));
        $city = (new City())
            ->setCountry($country)
            ->setName('Existing Geo City ' . $suffix)
            ->setSlug('existing-geo-city-' . strtolower($suffix));
        $this->entityManager()->persist($country);
        $this->entityManager()->persist($state);
        $this->entityManager()->persist($city);
        $this->entityManager()->flush();
        $cityId = $city->getId();

        $summary = $this->serviceWithProviders([
            new StaticDestinationProvider('geonames', 'GeoNames', [
                new DestinationCandidate(
                    provider: 'geonames',
                    type: DestinationEntityType::CITY,
                    name: 'Existing Geo City ' . $suffix,
                    countryName: $iso2,
                    nameFa: 'Ø´Ù‡Ø± Ù…ÙˆØ¬ÙˆØ¯',
                    externalId: 'geo-existing-city-' . $suffix,
                    admin1Code: '34',
                    rawData: ['source' => 'cities15000'],
                ),
            ]),
        ])->import(new DestinationImportRequest(DestinationEntityType::CITY, $iso2, null, ['geonames'], true));
        $this->entityManager()->clear();
        $refreshedCity = $this->entityManager()->getRepository(City::class)->find($cityId);

        self::assertSame(0, $summary->created);
        self::assertSame(1, $summary->updated);
        self::assertSame(1, $this->countBy(City::class, ['name' => 'Existing Geo City ' . $suffix]));
        self::assertInstanceOf(City::class, $refreshedCity);
        self::assertSame($cityId, $refreshedCity->getId());
        self::assertInstanceOf(State::class, $refreshedCity->getState());
        self::assertSame('34', $refreshedCity->getState()->getCode());
        self::assertSame('Ø´Ù‡Ø± Ù…ÙˆØ¬ÙˆØ¯', $refreshedCity->getNameFa());
    }

    public function testRefreshExistingRecordKeepsCanonicalIdAndPersistsSourceReference(): void
    {
        self::bootKernel();
        $suffix = self::uniqueSuffix();
        $service = $this->serviceWithProviders([
            new StaticDestinationProvider('booking', 'Booking', self::istanbulCandidates('booking', $suffix)),
        ]);
        $service->import(self::cityRequest($suffix, ['booking']));
        $city = $this->city($suffix);
        $district = $this->district($suffix);
        $cityId = $city->getId();
        $districtId = $district->getId();

        $refreshedService = $this->serviceWithProviders([
            new StaticDestinationProvider('booking', 'Booking', self::istanbulCandidates('booking', $suffix, 'استانبول')),
        ]);
        $summary = $refreshedService->import(new DestinationImportRequest(
            targetType: DestinationEntityType::CITY,
            countryName: 'Turkey ' . $suffix,
            cityName: 'Istanbul ' . $suffix,
            providerCodes: ['booking'],
            refresh: true,
        ));

        self::assertSame($cityId, $this->city($suffix)->getId());
        self::assertSame($districtId, $this->district($suffix)->getId());
        self::assertGreaterThanOrEqual(1, $summary->updated);
        self::assertSame(1, $this->countBy(DestinationSourceReference::class, [
            'source' => 'booking',
            'canonicalType' => DestinationEntityType::DISTRICT,
            'externalId' => 'booking-taksim-' . $suffix,
        ]));
    }

    public function testAirportRefreshBySourceReferenceKeepsIdAndUpdatesSourceOwnedFields(): void
    {
        self::bootKernel();
        $suffix = self::uniqueSuffix();
        $externalId = 'ourairports:airport:refresh-' . $suffix;
        $iataCode = $this->unusedIataCode();
        $service = $this->serviceWithProviders([
            new StaticDestinationProvider('ourairports', 'OurAirports', [
                self::countryCandidate('ourairports', $suffix),
                self::cityCandidate('ourairports', $suffix),
                new DestinationCandidate(
                    provider: 'ourairports',
                    type: DestinationEntityType::AIRPORT,
                    name: 'Refresh Airport ' . $suffix,
                    countryName: 'Turkey ' . $suffix,
                    cityName: 'Istanbul ' . $suffix,
                    externalId: $externalId,
                    iataCode: $iataCode,
                    icaoCode: 'A' . $iataCode,
                    latitude: '1.0000000',
                    longitude: '2.0000000',
                    rawData: ['kind' => 'airport', 'version' => 1],
                ),
            ]),
        ]);
        $service->import(self::cityRequest($suffix, ['ourairports']));
        $airport = $this->entityManager()->getRepository(Airport::class)->findOneBy(['name' => 'Refresh Airport ' . $suffix]);
        self::assertInstanceOf(Airport::class, $airport);
        $airportId = $airport->getId();

        $refreshedService = $this->serviceWithProviders([
            new StaticDestinationProvider('ourairports', 'OurAirports', [
                self::countryCandidate('ourairports', $suffix),
                self::cityCandidate('ourairports', $suffix),
                new DestinationCandidate(
                    provider: 'ourairports',
                    type: DestinationEntityType::AIRPORT,
                    name: 'Refresh Airport ' . $suffix,
                    countryName: 'Turkey ' . $suffix,
                    cityName: 'Istanbul ' . $suffix,
                    externalId: $externalId,
                    iataCode: $iataCode,
                    icaoCode: 'B' . $iataCode,
                    latitude: '3.0000000',
                    longitude: '4.0000000',
                    rawData: ['kind' => 'airport', 'version' => 2],
                ),
            ]),
        ]);
        $summary = $refreshedService->import(self::cityRequest($suffix, ['ourairports']));
        $refreshedAirport = $this->entityManager()->getRepository(Airport::class)->find($airportId);

        self::assertInstanceOf(Airport::class, $refreshedAirport);
        self::assertSame($airportId, $refreshedAirport->getId());
        self::assertSame('B' . $iataCode, $refreshedAirport->getIcaoCode());
        self::assertSame('3.0000000', $refreshedAirport->getLatitude());
        self::assertSame('4.0000000', $refreshedAirport->getLongitude());
        self::assertGreaterThanOrEqual(1, $summary->updated);
    }

    public function testAirportNameFallbackDoesNotMergeConflictingAirportCodes(): void
    {
        self::bootKernel();
        $suffix = self::uniqueSuffix();
        $firstIata = $this->unusedIataCode();
        do {
            $secondIata = $this->unusedIataCode();
        } while ($secondIata === $firstIata);
        $service = $this->serviceWithProviders([
            new StaticDestinationProvider('ourairports', 'OurAirports', [
                self::countryCandidate('ourairports', $suffix),
                self::cityCandidate('ourairports', $suffix),
                new DestinationCandidate(
                    provider: 'ourairports',
                    type: DestinationEntityType::AIRPORT,
                    name: 'Shared Municipal Airport ' . $suffix,
                    countryName: 'Turkey ' . $suffix,
                    cityName: 'Istanbul ' . $suffix,
                    externalId: 'ourairports:airport:first-' . $suffix,
                    iataCode: $firstIata,
                    icaoCode: 'A' . $firstIata,
                    rawData: ['kind' => 'airport', 'source_id' => 'first'],
                ),
                new DestinationCandidate(
                    provider: 'ourairports',
                    type: DestinationEntityType::AIRPORT,
                    name: 'Shared Municipal Airport ' . $suffix,
                    countryName: 'Turkey ' . $suffix,
                    cityName: 'Istanbul ' . $suffix,
                    externalId: 'ourairports:airport:second-' . $suffix,
                    iataCode: $secondIata,
                    icaoCode: 'B' . $secondIata,
                    rawData: ['kind' => 'airport', 'source_id' => 'second'],
                ),
            ]),
        ]);

        $request = self::cityRequest($suffix, ['ourairports']);
        $first = $service->import($request);
        $second = $service->import($request);
        $city = $this->city($suffix);

        self::assertSame(4, $first->created);
        self::assertSame(0, $second->created);
        self::assertSame(0, $second->updated);
        self::assertSame(2, $this->countBy(Airport::class, ['city' => $city, 'name' => 'Shared Municipal Airport ' . $suffix]));
        self::assertSame(1, $this->countBy(Airport::class, ['iataCode' => $firstIata]));
        self::assertSame(1, $this->countBy(Airport::class, ['iataCode' => $secondIata]));
    }

    /**
     * @param DestinationProviderInterface[] $providers
     */
    private function serviceWithProviders(array $providers): DestinationImportService
    {
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        return new DestinationImportService(
            new DestinationProviderRegistry($providers),
            new DestinationNormalizer(),
            $em,
            $container->get(CountryRepository::class),
            $container->get(StateRepository::class),
            $container->get(CityRepository::class),
            $container->get(DistrictRepository::class),
            $container->get(AirportRepository::class),
            $container->get(DestinationSourceReferenceRepository::class),
        );
    }

    /**
     * @param class-string $className
     * @param array<string, mixed> $criteria
     */
    private function countBy(string $className, array $criteria): int
    {
        return $this->entityManager()->getRepository($className)->count($criteria);
    }

    private function city(string $suffix): City
    {
        $city = $this->entityManager()->getRepository(City::class)->findOneBy(['name' => 'Istanbul ' . $suffix]);
        self::assertInstanceOf(City::class, $city);

        return $city;
    }

    private function district(string $suffix): District
    {
        $district = $this->entityManager()->getRepository(District::class)->findOneBy(['name' => 'Taksim Area']);
        self::assertInstanceOf(District::class, $district, $suffix);

        return $district;
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
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

    /**
     * @param string[] $providers
     */
    private static function cityRequest(string $suffix, array $providers): DestinationImportRequest
    {
        return new DestinationImportRequest(
            targetType: DestinationEntityType::CITY,
            countryName: 'Turkey ' . $suffix,
            cityName: 'Istanbul ' . $suffix,
            providerCodes: $providers,
            refresh: true,
        );
    }

    /**
     * @return DestinationCandidate[]
     */
    private static function istanbulCandidates(string $provider, string $suffix, ?string $cityNameFa = null): array
    {
        return [
            self::countryCandidate($provider, $suffix),
            self::cityCandidate($provider, $suffix, $cityNameFa),
            self::districtCandidate($provider, $suffix, 'Taksim Area', $provider . '-taksim-' . $suffix),
            new DestinationCandidate(
                provider: $provider,
                type: DestinationEntityType::AIRPORT,
                name: 'Istanbul Airport ' . $suffix,
                countryName: 'Turkey ' . $suffix,
                cityName: 'Istanbul ' . $suffix,
                externalId: $provider . '-airport-' . $suffix,
                iataCode: substr($suffix, 0, 3),
                icaoCode: 'A' . substr($suffix, 0, 3),
                latitude: '41.2752780',
                longitude: '28.7519440',
                rawData: ['kind' => 'airport'],
            ),
        ];
    }

    private static function countryCandidate(string $provider, string $suffix): DestinationCandidate
    {
        return new DestinationCandidate(
            provider: $provider,
            type: DestinationEntityType::COUNTRY,
            name: 'Turkey ' . $suffix,
            externalId: $provider . '-country-' . $suffix,
            rawData: ['kind' => 'country'],
        );
    }

    private static function cityCandidate(string $provider, string $suffix, ?string $nameFa = null): DestinationCandidate
    {
        return new DestinationCandidate(
            provider: $provider,
            type: DestinationEntityType::CITY,
            name: 'Istanbul ' . $suffix,
            countryName: 'Turkey ' . $suffix,
            nameFa: $nameFa,
            externalId: $provider . '-city-' . $suffix,
            rawData: ['kind' => 'city'],
        );
    }

    private static function districtCandidate(string $provider, string $suffix, string $name, string $externalId): DestinationCandidate
    {
        return new DestinationCandidate(
            provider: $provider,
            type: DestinationEntityType::DISTRICT,
            name: $name,
            countryName: 'Turkey ' . $suffix,
            cityName: 'Istanbul ' . $suffix,
            externalId: $externalId,
            rawData: ['kind' => 'district'],
        );
    }

    private static function uniqueSuffix(): string
    {
        $letters = 'Q';
        for ($index = 0; $index < 5; $index++) {
            $letters .= chr(random_int(65, 90));
        }

        return $letters;
    }
}

final class StaticDestinationProvider implements DestinationProviderInterface
{
    /**
     * @param DestinationCandidate[] $candidates
     */
    public function __construct(
        private readonly string $code,
        private readonly string $label,
        private readonly array $candidates,
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function discover(DestinationImportRequest $request): DestinationProviderResult
    {
        return DestinationProviderResult::success($this->code, $this->candidates, ['target' => $request->targetType]);
    }
}

final class FailingDestinationProvider implements DestinationProviderInterface
{
    public function __construct(
        private readonly string $code,
        private readonly string $label,
        private readonly string $error,
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function discover(DestinationImportRequest $request): DestinationProviderResult
    {
        return DestinationProviderResult::failure($this->code, [$this->error], ['target' => $request->targetType]);
    }
}
