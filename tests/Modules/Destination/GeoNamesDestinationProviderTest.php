<?php

namespace App\Tests\Modules\Destination;

use App\Modules\Destination\Provider\GeoNamesDestinationProvider;
use App\Modules\Destination\ValueObject\DestinationEntityType;
use App\Modules\Destination\ValueObject\DestinationImportRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GeoNamesDestinationProviderTest extends TestCase
{
    private string $cacheDirectory;

    protected function setUp(): void
    {
        $this->cacheDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'alefbayesafar-geonames-test-' . bin2hex(random_bytes(4));
        mkdir($this->cacheDirectory . DIRECTORY_SEPARATOR . 'alternatenames', 0777, true);
    }

    public function testParsesCountriesFromCountryInfo(): void
    {
        file_put_contents($this->cacheDirectory . DIRECTORY_SEPARATOR . 'countryInfo.txt', implode("\n", [
            '#ISO ISO3 etc',
            "TR\tTUR\t792\tTU\tTurkey\tAnkara\t780580\t77804122\tAS\t.tr\tTRY\tLira\t90\t#####\t^(\d{5})$\ttr-TR,ku,diq,az,ava\t298795",
            "DE\tDEU\t276\tGM\tGermany\tBerlin\t357021\t83783942\tEU\t.de\tEUR\tEuro\t49\t#####\t^(\d{5})$\tde\t2921044",
        ]));

        $result = $this->provider()->discover(new DestinationImportRequest(DestinationEntityType::COUNTRY, 'GLOBAL', null, ['geonames'], false));

        self::assertTrue($result->success);
        self::assertCount(2, $result->candidates);
        self::assertSame('298795', $result->candidates[0]->externalId);
        self::assertSame('TR', $result->candidates[0]->iso2);
        self::assertSame('TUR', $result->candidates[0]->iso3);
    }

    public function testParsesAdmin1StatesWithPersianAlternateName(): void
    {
        file_put_contents($this->cacheDirectory . DIRECTORY_SEPARATOR . 'admin1CodesASCII.txt', "TR.34\tIstanbul\tIstanbul\t745042\nDE.07\tNorth Rhine-Westphalia\tNorth Rhine-Westphalia\t2861876\n");
        $this->zip('alternatenames/TR.zip', 'TR.txt', "1\t745042\tfa\tاستانبول\t1\t0\t0\t0\t\n");

        $result = $this->provider()->discover(new DestinationImportRequest(DestinationEntityType::STATE, 'TR', null, ['geonames'], false));

        self::assertTrue($result->success);
        self::assertCount(1, $result->candidates);
        self::assertSame(DestinationEntityType::STATE, $result->candidates[0]->type);
        self::assertSame('34', $result->candidates[0]->admin1Code);
        self::assertSame('استانبول', $result->candidates[0]->nameFa);
    }

    public function testParsesCities15000AndFiltersFeatureClassAndCountry(): void
    {
        $this->zip('cities15000.zip', 'cities15000.txt', implode("\n", [
            "745044\tIstanbul\tIstanbul\tIstanbul,استانبول\t41.01384\t28.94966\tP\tPPLA\tTR\t\t34\t\t\t\t14804116\t\t40\tEurope/Istanbul\t2026-01-01",
            "2886242\tKoeln\tKoeln\tKöln,Cologne\t50.93333\t6.95\tP\tPPLA\tDE\t\t07\t\t\t\t963395\t\t58\tEurope/Berlin\t2026-01-01",
            "1\tMountain\tMountain\t\t1\t1\tT\tMT\tTR\t\t34\t\t\t\t0\t\t0\tEurope/Istanbul\t2026-01-01",
        ]));
        $this->zip('alternatenames/TR.zip', 'TR.txt', "1\t745044\tfa\tاستانبول\t1\t0\t0\t0\t\n");

        $result = $this->provider()->discover(new DestinationImportRequest(DestinationEntityType::CITY, 'TR', null, ['geonames'], false));

        self::assertTrue($result->success);
        self::assertCount(1, $result->candidates);
        self::assertSame('Istanbul', $result->candidates[0]->name);
        self::assertSame('34', $result->candidates[0]->admin1Code);
        self::assertSame('استانبول', $result->candidates[0]->nameFa);
        self::assertSame('cities15000', $result->candidates[0]->rawData['dataset']);
    }

    public function testDownloadFailureIsProviderFailureWhenNoCacheExists(): void
    {
        $provider = new GeoNamesDestinationProvider(new MockHttpClient([new MockResponse('', ['http_code' => 503])]), $this->cacheDirectory);

        $result = $provider->discover(new DestinationImportRequest(DestinationEntityType::COUNTRY, 'GLOBAL', null, ['geonames'], true));

        self::assertFalse($result->success);
        self::assertSame(['GeoNames countryInfo.txt download failed.'], $result->errors);
    }

    private function provider(): GeoNamesDestinationProvider
    {
        return new GeoNamesDestinationProvider(new MockHttpClient(), $this->cacheDirectory);
    }

    private function zip(string $relativePath, string $memberName, string $content): void
    {
        $path = $this->cacheDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path, \ZipArchive::CREATE));
        $zip->addFromString($memberName, $content);
        $zip->close();
    }
}
