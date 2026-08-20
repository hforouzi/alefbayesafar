<?php

namespace App\Modules\Destination\Provider;

use App\Modules\Destination\ValueObject\DestinationCandidate;
use App\Modules\Destination\ValueObject\DestinationEntityType;
use App\Modules\Destination\ValueObject\DestinationImportRequest;
use App\Modules\Destination\ValueObject\DestinationProviderResult;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GeoNamesDestinationProvider implements DestinationProviderInterface
{
    private const ROOT_URL = 'https://download.geonames.org/export/dump/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $cacheDirectory,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function getCode(): string
    {
        return 'geonames';
    }

    public function getLabel(): string
    {
        return 'GeoNames';
    }

    public function discover(DestinationImportRequest $request): DestinationProviderResult
    {
        return match ($request->targetType) {
            DestinationEntityType::COUNTRY => $this->countries($request),
            DestinationEntityType::STATE => $this->states($request),
            DestinationEntityType::CITY => $this->cities($request),
            default => DestinationProviderResult::failure($this->getCode(), ['GeoNames supports country, state, and city bootstrap only.']),
        };
    }

    private function countries(DestinationImportRequest $request): DestinationProviderResult
    {
        $path = $this->ensureDownloaded('countryInfo.txt', $request->refresh);
        if ($path === null) {
            return DestinationProviderResult::failure($this->getCode(), ['GeoNames countryInfo.txt download failed.']);
        }

        $candidates = [];
        foreach ($this->tabRows($path) as $row) {
            if (\count($row) < 17 || str_starts_with((string) ($row[0] ?? ''), '#')) {
                continue;
            }

            $iso2 = strtoupper(trim($row[0]));
            $iso3 = strtoupper(trim($row[1]));
            $name = trim($row[4]);
            $geonameId = trim($row[16]);
            if ($iso2 === '' || $name === '' || $geonameId === '') {
                continue;
            }

            $candidates[] = new DestinationCandidate(
                provider: $this->getCode(),
                type: DestinationEntityType::COUNTRY,
                name: $name,
                externalId: $geonameId,
                sourceUrl: self::ROOT_URL . 'countryInfo.txt',
                sourceTitle: $name,
                iso2: $iso2,
                iso3: $iso3 !== '' ? $iso3 : null,
                rawData: [
                    'geonameid' => $geonameId,
                    'iso2' => $iso2,
                    'iso3' => $iso3,
                    'population' => $row[7] ?? null,
                    'continent' => $row[8] ?? null,
                    'source_file' => 'countryInfo.txt',
                ],
            );
        }

        return DestinationProviderResult::success($this->getCode(), $candidates, ['source_file' => $path]);
    }

    private function states(DestinationImportRequest $request): DestinationProviderResult
    {
        $path = $this->ensureDownloaded('admin1CodesASCII.txt', $request->refresh);
        if ($path === null) {
            return DestinationProviderResult::failure($this->getCode(), ['GeoNames admin1CodesASCII.txt download failed.']);
        }

        $targetIso2 = $this->targetIso2($request->countryName);
        $alternateNames = $targetIso2 !== null ? $this->alternateNamesForCountry($targetIso2, $request->refresh) : [];
        $candidates = [];
        foreach ($this->tabRows($path) as $row) {
            if (\count($row) < 4) {
                continue;
            }

            [$compoundCode, $name, $asciiName, $geonameId] = array_pad($row, 4, '');
            $parts = explode('.', trim($compoundCode), 2);
            if (\count($parts) !== 2) {
                continue;
            }

            [$iso2, $admin1Code] = $parts;
            $iso2 = strtoupper($iso2);
            if ($targetIso2 !== null && $iso2 !== $targetIso2) {
                continue;
            }

            $stateName = trim($name) !== '' ? trim($name) : trim($asciiName);
            if ($stateName === '' || trim($geonameId) === '') {
                continue;
            }

            $candidates[] = new DestinationCandidate(
                provider: $this->getCode(),
                type: DestinationEntityType::STATE,
                name: $stateName,
                countryName: $iso2,
                nameFa: $alternateNames[trim($geonameId)] ?? null,
                externalId: trim($geonameId),
                sourceUrl: self::ROOT_URL . 'admin1CodesASCII.txt',
                sourceTitle: $stateName,
                admin1Code: strtoupper(trim($admin1Code)),
                rawData: [
                    'geonameid' => trim($geonameId),
                    'compound_code' => trim($compoundCode),
                    'iso_country' => $iso2,
                    'admin1_code' => strtoupper(trim($admin1Code)),
                    'ascii_name' => trim($asciiName),
                    'source_file' => 'admin1CodesASCII.txt',
                ],
            );
        }

        return DestinationProviderResult::success($this->getCode(), $candidates, ['source_file' => $path, 'country' => $targetIso2]);
    }

    private function cities(DestinationImportRequest $request): DestinationProviderResult
    {
        $path = $this->ensureDownloaded('cities15000.zip', $request->refresh);
        if ($path === null) {
            return DestinationProviderResult::failure($this->getCode(), ['GeoNames cities15000.zip download failed.']);
        }

        $textPath = $this->extractZip($path, 'cities15000.txt');
        if ($textPath === null) {
            return DestinationProviderResult::failure($this->getCode(), ['Could not extract GeoNames cities15000.txt.']);
        }

        $targetIso2 = $this->targetIso2($request->countryName);
        $alternateNames = $targetIso2 !== null ? $this->alternateNamesForCountry($targetIso2, $request->refresh) : [];
        $candidates = [];
        foreach ($this->tabRows($textPath) as $row) {
            if (\count($row) < 19) {
                continue;
            }

            $featureClass = trim($row[6]);
            $countryCode = strtoupper(trim($row[8]));
            $admin1Code = strtoupper(trim($row[10]));
            if ($featureClass !== 'P' || $countryCode === '' || $admin1Code === '00') {
                continue;
            }

            if ($targetIso2 !== null && $countryCode !== $targetIso2) {
                continue;
            }

            $geonameId = trim($row[0]);
            $name = trim($row[1]);
            if ($geonameId === '' || $name === '') {
                continue;
            }

            $candidates[] = new DestinationCandidate(
                provider: $this->getCode(),
                type: DestinationEntityType::CITY,
                name: $name,
                countryName: $countryCode,
                nameFa: $alternateNames[$geonameId] ?? null,
                externalId: $geonameId,
                sourceUrl: self::ROOT_URL . 'cities15000.zip',
                sourceTitle: $name,
                admin1Code: $admin1Code,
                latitude: $row[4] !== '' ? $row[4] : null,
                longitude: $row[5] !== '' ? $row[5] : null,
                dataUpdatedAt: $this->dateOrNull($row[18] ?? ''),
                rawData: [
                    'geonameid' => $geonameId,
                    'ascii_name' => trim($row[2]),
                    'alternate_names' => trim($row[3]),
                    'latitude' => trim($row[4]),
                    'longitude' => trim($row[5]),
                    'feature_class' => $featureClass,
                    'feature_code' => trim($row[7]),
                    'iso_country' => $countryCode,
                    'admin1_code' => $admin1Code,
                    'population' => trim($row[14]),
                    'timezone' => trim($row[17]),
                    'modification_date' => trim($row[18]),
                    'dataset' => 'cities15000',
                ],
            );
        }

        return DestinationProviderResult::success($this->getCode(), $candidates, ['source_file' => $path, 'dataset' => 'cities15000', 'country' => $targetIso2]);
    }

    private function ensureDownloaded(string $fileName, bool $refresh): ?string
    {
        $path = rtrim($this->cacheDirectory, '/\\') . DIRECTORY_SEPARATOR . $fileName;
        if (!$refresh && is_readable($path)) {
            return $path;
        }

        $this->filesystem->mkdir(dirname($path));
        $temporaryPath = $path . '.tmp';
        try {
            $response = $this->httpClient->request('GET', self::ROOT_URL . $fileName, [
                'headers' => ['User-Agent' => 'AlefBayeSafarBot/0.1 (+destination geonames import)'],
                'timeout' => 120,
            ]);
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                return is_readable($path) ? $path : null;
            }

            $output = fopen($temporaryPath, 'wb');
            if (!\is_resource($output)) {
                return is_readable($path) ? $path : null;
            }

            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($output, $chunk->getContent());
            }
            fclose($output);
        } catch (TransportExceptionInterface) {
            return is_readable($path) ? $path : null;
        }

        $checksum = hash_file('sha256', $temporaryPath);
        $this->filesystem->rename($temporaryPath, $path, true);
        file_put_contents($path . '.metadata.json', json_encode([
            'success' => true,
            'downloadedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'sourceUrl' => self::ROOT_URL . $fileName,
            'fileSize' => filesize($path) ?: 0,
            'checksum' => $checksum !== false ? $checksum : null,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $path;
    }

    private function extractZip(string $zipPath, string $memberName): ?string
    {
        $extractPath = dirname($zipPath) . DIRECTORY_SEPARATOR . $memberName;
        if (is_readable($extractPath) && filemtime($extractPath) >= filemtime($zipPath)) {
            return $extractPath;
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return null;
        }

        $content = $zip->getFromName($memberName);
        $zip->close();
        if ($content === false) {
            return null;
        }

        file_put_contents($extractPath, $content);

        return $extractPath;
    }

    /**
     * @return iterable<int, string[]>
     */
    private function tabRows(string $path): iterable
    {
        $file = new \SplFileObject($path, 'rb');
        while (!$file->eof()) {
            $line = trim((string) $file->fgets(), "\r\n");
            if ($line === '') {
                continue;
            }

            yield explode("\t", $line);
        }
    }

    /**
     * @return array<string, string>
     */
    private function alternateNamesForCountry(string $iso2, bool $refresh): array
    {
        $fileName = 'alternatenames/' . strtoupper($iso2) . '.zip';
        $zipPath = $this->ensureDownloaded($fileName, $refresh);
        if ($zipPath === null) {
            return [];
        }

        $textPath = $this->extractZip($zipPath, strtoupper($iso2) . '.txt');
        if ($textPath === null) {
            return [];
        }

        $names = [];
        foreach ($this->tabRows($textPath) as $row) {
            if (\count($row) < 4 || trim($row[2]) !== 'fa') {
                continue;
            }

            $geonameId = trim($row[1]);
            $name = trim($row[3]);
            if ($geonameId !== '' && $name !== '' && !isset($names[$geonameId])) {
                $names[$geonameId] = $name;
            }
        }

        return $names;
    }

    private function targetIso2(string $countryName): ?string
    {
        $countryName = strtoupper(trim($countryName));
        if ($countryName === '' || $countryName === 'GLOBAL' || $countryName === '*') {
            return null;
        }

        return preg_match('/^[A-Z]{2}$/', $countryName) === 1 ? $countryName : null;
    }

    private function dateOrNull(string $date): ?\DateTimeImmutable
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($date);
        } catch (\Exception) {
            return null;
        }
    }
}
