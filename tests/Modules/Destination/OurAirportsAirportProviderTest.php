<?php

namespace App\Tests\Modules\Destination;

use App\Modules\Destination\Provider\OurAirportsAirportProvider;
use App\Modules\Destination\ValueObject\DestinationEntityType;
use App\Modules\Destination\ValueObject\DestinationImportRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OurAirportsAirportProviderTest extends TestCase
{
    private string $cachePath;

    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '\\alefbayesafar-ourairports-' . bin2hex(random_bytes(6)) . '.csv';
    }

    protected function tearDown(): void
    {
        @unlink($this->cachePath);
        @unlink($this->cachePath . '.metadata.json');
        @unlink($this->cachePath . '.tmp');
    }

    public function testDownloadsParsesAndMapsAirportRows(): void
    {
        $provider = $this->providerWithResponses([
            new MockResponse($this->csv([
                ['317457', 'LTFM', 'large_airport', 'İstanbul Airport', '41.274874', '28.732136', 'TR', 'Istanbul', 'LTFM', 'IST', 'LTFM'],
                ['4569', 'LTFJ', 'large_airport', 'Istanbul Sabiha Gökçen International Airport', '40.898602', '29.3092', 'TR', 'Pendik, Istanbul', 'LTFJ', 'SAW', 'LTFJ'],
                ['1', 'TR-HEL', 'heliport', 'Ignored Heliport', '1', '1', 'TR', 'Istanbul', '', '', 'TRHL'],
                ['2', 'TR-SML', 'small_airport', 'Ignored Small', '1', '1', 'TR', 'Istanbul', '', 'ISM', 'TRSM'],
                ['3', 'TR-NOCITY', 'large_airport', 'No City Airport', '1', '1', 'TR', '', 'LTAA', 'NOC', 'LTAA'],
                ['4', 'DE-TEST', 'large_airport', 'Germany Airport', '1', '1', 'DE', 'Berlin', 'EDDB', 'BER', 'EDDB'],
            ]), ['http_code' => 200]),
        ]);

        $result = $provider->discover($this->request(refresh: true));

        self::assertTrue($result->success);
        self::assertFileExists($this->cachePath);
        self::assertFileExists($this->cachePath . '.metadata.json');
        self::assertSame('ourairports', $result->provider);
        self::assertCount(3, $result->candidates);
        self::assertSame(DestinationEntityType::AIRPORT, $result->candidates[0]->type);
        self::assertSame('TR', $result->candidates[0]->countryName);
        self::assertSame('IST', $result->candidates[0]->iataCode);
        self::assertSame('LTFM', $result->candidates[0]->icaoCode);
        self::assertSame('SAW', $result->candidates[1]->iataCode);
        self::assertSame('LTFJ', $result->candidates[1]->icaoCode);
        self::assertSame('Istanbul', $result->candidates[1]->cityName);
        self::assertSame('ourairports:airport:4569', $result->candidates[1]->externalId);
        self::assertSame('BER', $result->candidates[2]->iataCode);
        self::assertSame('DE', $result->candidates[2]->countryName);
        self::assertSame(3, $result->diagnostics['included_airports']);
        self::assertSame(4, $result->diagnostics['qualifying_airports']);
        self::assertSame(2, $result->diagnostics['skipped']['type']);
        self::assertSame(1, $result->diagnostics['skipped']['city']);
    }

    public function testGpsCodeIsUsedWhenIcaoCodeIsMissing(): void
    {
        $provider = $this->providerWithResponses([
            new MockResponse($this->csv([
                ['100', 'TR-GPS', 'medium_airport', 'GPS Airport', '1', '2', 'TR', 'Istanbul', '', 'GPS', 'LTGP'],
            ]), ['http_code' => 200]),
        ]);

        $result = $provider->discover($this->request(refresh: true));

        self::assertTrue($result->success);
        self::assertSame('GPS', $result->candidates[0]->iataCode);
        self::assertSame('LTGP', $result->candidates[0]->icaoCode);
    }

    public function testOptionalCountryAndCityScopeFiltersGlobalCsv(): void
    {
        $provider = $this->providerWithResponses([
            new MockResponse($this->csv([
                ['317457', 'LTFM', 'large_airport', 'Ä°stanbul Airport', '41.274874', '28.732136', 'TR', 'Istanbul', 'LTFM', 'IST', 'LTFM'],
                ['4569', 'LTFJ', 'large_airport', 'Istanbul Sabiha GÃ¶kÃ§en International Airport', '40.898602', '29.3092', 'TR', 'Pendik, Istanbul', 'LTFJ', 'SAW', 'LTFJ'],
                ['4', 'DE-TEST', 'large_airport', 'Germany Airport', '1', '1', 'DE', 'Berlin', 'EDDB', 'BER', 'EDDB'],
            ]), ['http_code' => 200]),
        ]);

        $result = $provider->discover(new DestinationImportRequest(
            targetType: DestinationEntityType::AIRPORT,
            countryName: 'Turkey',
            cityName: 'Istanbul',
            providerCodes: ['ourairports'],
            refresh: true,
        ));

        self::assertTrue($result->success);
        self::assertCount(2, $result->candidates);
        self::assertSame(['IST', 'SAW'], array_map(static fn ($candidate): ?string => $candidate->iataCode, $result->candidates));
        self::assertSame(1, $result->diagnostics['skipped']['country']);
    }

    public function testMalformedCsvRowDoesNotBreakImport(): void
    {
        $csv = $this->header() . "\n" .
            '"100","TR-GPS","medium_airport","GPS Airport","1","2",,,,,,,,,,,,,';
        $provider = $this->providerWithResponses([
            new MockResponse($csv, ['http_code' => 200]),
        ]);

        $result = $provider->discover($this->request(refresh: true));

        self::assertFalse($result->success);
        self::assertSame(['No included OurAirports airport rows matched the requested scope.'], $result->errors);
    }

    public function testDownloadFailureIsNotSuccessfulEmptyImportAndPreservesPreviousCache(): void
    {
        file_put_contents($this->cachePath, $this->csv([
            ['100', 'TR-GPS', 'medium_airport', 'GPS Airport', '1', '2', 'TR', 'Istanbul', '', 'GPS', 'LTGP'],
        ]));
        $provider = $this->providerWithResponses([
            new MockResponse('blocked', ['http_code' => 503]),
        ]);

        $result = $provider->discover($this->request(refresh: true));

        self::assertFalse($result->success);
        self::assertSame(['OurAirports download returned HTTP 503.'], $result->errors);
        self::assertFileExists($this->cachePath);
    }

    /**
     * @param MockResponse[] $responses
     */
    private function providerWithResponses(array $responses): OurAirportsAirportProvider
    {
        return new OurAirportsAirportProvider(new MockHttpClient($responses), 'https://example.test/airports.csv', $this->cachePath);
    }

    private function request(bool $refresh): DestinationImportRequest
    {
        return new DestinationImportRequest(
            targetType: DestinationEntityType::AIRPORT,
            countryName: 'GLOBAL',
            cityName: null,
            providerCodes: ['ourairports'],
            refresh: $refresh,
        );
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string, 7: string, 8: string, 9: string, 10: string}> $rows
     */
    private function csv(array $rows): string
    {
        $lines = [$this->header()];
        foreach ($rows as $row) {
            $lines[] = sprintf(
                '"%s","%s","%s","%s","%s","%s",0,"EU","%s","TR-34","%s","yes","%s","%s","%s",,,,',
                ...$row
            );
        }

        return implode("\n", $lines) . "\n";
    }

    private function header(): string
    {
        return '"id","ident","type","name","latitude_deg","longitude_deg","elevation_ft","continent","iso_country","iso_region","municipality","scheduled_service","icao_code","iata_code","gps_code","local_code","home_link","wikipedia_link","keywords"';
    }
}
