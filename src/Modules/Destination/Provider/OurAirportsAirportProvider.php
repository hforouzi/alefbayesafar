<?php

namespace App\Modules\Destination\Provider;

use App\Modules\Destination\ValueObject\DestinationCandidate;
use App\Modules\Destination\ValueObject\DestinationEntityType;
use App\Modules\Destination\ValueObject\DestinationImportRequest;
use App\Modules\Destination\ValueObject\DestinationProviderResult;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Countries;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OurAirportsAirportProvider implements DestinationProviderInterface
{
    private const INCLUDED_TYPES = ['large_airport', 'medium_airport'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $sourceUrl,
        private readonly string $cachePath,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function getCode(): string
    {
        return 'ourairports';
    }

    public function getLabel(): string
    {
        return 'OurAirports';
    }

    public function discover(DestinationImportRequest $request): DestinationProviderResult
    {
        if ($request->targetType !== DestinationEntityType::AIRPORT) {
            return DestinationProviderResult::failure($this->getCode(), ['OurAirports imports only airport records.']);
        }

        $downloadMetadata = [];
        if ($request->refresh || !is_file($this->cachePath)) {
            $downloadMetadata = $this->downloadCsv();
            if ($downloadMetadata['success'] !== true) {
                return DestinationProviderResult::failure($this->getCode(), [$downloadMetadata['error'] ?? 'OurAirports CSV download failed.'], [
                    'source_url' => $this->sourceUrl,
                    'cache_path' => $this->cachePath,
                    'previous_cache_exists' => is_file($this->cachePath),
                ]);
            }
        }

        if (!is_readable($this->cachePath)) {
            return DestinationProviderResult::failure($this->getCode(), ['OurAirports cached CSV is not readable.'], [
                'source_url' => $this->sourceUrl,
                'cache_path' => $this->cachePath,
            ]);
        }

        $targetIso2 = $this->resolveCountryIso2($request->countryName);
        if (!$this->isGlobalScope($request->countryName) && $targetIso2 === null) {
            return DestinationProviderResult::failure($this->getCode(), [sprintf('Could not resolve country ISO2 for "%s".', $request->countryName)]);
        }

        $candidates = [];
        $included = 0;
        $qualifying = 0;
        $skipped = [
            'type' => 0,
            'country' => 0,
            'city' => 0,
            'iata' => 0,
            'malformed' => 0,
        ];
        $sourceMetadata = $this->metadata();
        $targetCity = $this->normalizedTargetCity($request->cityName);

        foreach ($this->rows() as $row) {
            if (!$this->isRowShapeValid($row)) {
                $skipped['malformed']++;
                continue;
            }

            if (!\in_array((string) $row['type'], self::INCLUDED_TYPES, true)) {
                $skipped['type']++;
                continue;
            }

            $qualifying++;
            $rowIso2 = strtoupper(trim((string) ($row['iso_country'] ?? '')));
            if ($targetIso2 !== null && $rowIso2 !== $targetIso2) {
                $skipped['country']++;
                continue;
            }

            if (!\is_string($row['iso_country']) || !preg_match('/^[A-Z]{2}$/', $rowIso2)) {
                $skipped['country']++;
                continue;
            }

            $cityName = $this->resolveCityName((string) ($row['municipality'] ?? ''), $targetCity);
            if ($cityName === null) {
                $skipped['city']++;
                continue;
            }

            if ($targetCity !== null && $this->normalize($cityName) !== $this->normalize($targetCity)) {
                $skipped['city']++;
                continue;
            }

            $iataCode = $this->validIata((string) ($row['iata_code'] ?? ''));
            if ($iataCode === null) {
                $skipped['iata']++;
                continue;
            }

            $included++;
            $candidates[] = new DestinationCandidate(
                provider: $this->getCode(),
                type: DestinationEntityType::AIRPORT,
                name: (string) $row['name'],
                countryName: $rowIso2,
                cityName: $cityName,
                externalId: 'ourairports:airport:' . (string) $row['id'],
                sourceUrl: $this->sourceUrl,
                sourceTitle: (string) $row['name'],
                iataCode: $iataCode,
                icaoCode: $this->validIcao((string) ($row['icao_code'] ?? '')) ?? $this->validIcao((string) ($row['gps_code'] ?? '')),
                latitude: $this->decimalOrNull((string) ($row['latitude_deg'] ?? '')),
                longitude: $this->decimalOrNull((string) ($row['longitude_deg'] ?? '')),
                rawData: $sourceMetadata + [
                    'source_id' => (string) $row['id'],
                    'ident' => (string) $row['ident'],
                    'type' => (string) $row['type'],
                    'iso_country' => $rowIso2,
                    'municipality' => (string) $row['municipality'],
                    'iata_code' => (string) $row['iata_code'],
                    'icao_code' => (string) $row['icao_code'],
                    'gps_code' => (string) $row['gps_code'],
                ],
            );
        }

        if ($included === 0) {
            return DestinationProviderResult::failure($this->getCode(), ['No included OurAirports airport rows matched the requested scope.'], [
                'source_url' => $this->sourceUrl,
                'cache_path' => $this->cachePath,
                'iso_country' => $targetIso2 ?? 'GLOBAL',
                'qualifying_airports' => $qualifying,
                'skipped' => $skipped,
            ]);
        }

        return DestinationProviderResult::success($this->getCode(), $candidates, [
            'source_url' => $this->sourceUrl,
            'cache_path' => $this->cachePath,
            'iso_country' => $targetIso2 ?? 'GLOBAL',
            'qualifying_airports' => $qualifying,
            'included_airports' => $included,
            'skipped' => $skipped,
            'download' => $downloadMetadata,
            'metadata' => $sourceMetadata,
        ]);
    }

    /**
     * @return array{success: bool, downloadedAt?: string, fileSize?: int, checksum?: string, error?: string}
     */
    private function downloadCsv(): array
    {
        $directory = dirname($this->cachePath);
        $this->filesystem->mkdir($directory);
        $temporaryPath = $this->cachePath . '.tmp';

        try {
            $response = $this->httpClient->request('GET', $this->sourceUrl, [
                'headers' => [
                    'User-Agent' => 'AlefBayeSafarBot/0.1 (+destination catalog import)',
                    'Accept' => 'text/csv,*/*;q=0.8',
                ],
                'timeout' => 60,
            ]);
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                return ['success' => false, 'error' => sprintf('OurAirports download returned HTTP %d.', $statusCode)];
            }

            $output = fopen($temporaryPath, 'wb');
            if (!\is_resource($output)) {
                return ['success' => false, 'error' => 'Could not open OurAirports cache file for writing.'];
            }

            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($output, $chunk->getContent());
            }
            fclose($output);
        } catch (TransportExceptionInterface $exception) {
            return ['success' => false, 'error' => $exception->getMessage()];
        }

        $checksum = hash_file('sha256', $temporaryPath);
        if ($checksum === false) {
            $this->filesystem->remove($temporaryPath);

            return ['success' => false, 'error' => 'Could not checksum downloaded OurAirports CSV.'];
        }

        $this->filesystem->rename($temporaryPath, $this->cachePath, true);
        $metadata = [
            'success' => true,
            'downloadedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'sourceUrl' => $this->sourceUrl,
            'fileSize' => filesize($this->cachePath) ?: 0,
            'checksum' => $checksum,
        ];
        file_put_contents($this->cachePath . '.metadata.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $metadata;
    }

    /**
     * @return iterable<array<string, string|null>>
     */
    private function rows(): iterable
    {
        $file = new \SplFileObject($this->cachePath, 'rb');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
        $header = null;

        foreach ($file as $row) {
            if (!\is_array($row) || $row === [null]) {
                continue;
            }

            if ($header === null) {
                $header = array_map(static fn (mixed $value): string => (string) $value, $row);
                continue;
            }

            if (\count($row) !== \count($header)) {
                yield ['__malformed' => '1'];
                continue;
            }

            yield array_combine($header, array_map(static fn (mixed $value): ?string => $value !== null ? (string) $value : null, $row)) ?: ['__malformed' => '1'];
        }
    }

    /**
     * @param array<string, string|null> $row
     */
    private function isRowShapeValid(array $row): bool
    {
        foreach (['id', 'type', 'name', 'latitude_deg', 'longitude_deg', 'iso_country', 'municipality', 'iata_code'] as $field) {
            if (!array_key_exists($field, $row)) {
                return false;
            }
        }

        return !isset($row['__malformed']) && trim((string) $row['id']) !== '' && trim((string) $row['name']) !== '';
    }

    private function resolveCountryIso2(string $countryName): ?string
    {
        $countryName = trim($countryName);
        if ($this->isGlobalScope($countryName)) {
            return null;
        }

        if (preg_match('/^[A-Za-z]{2}$/', $countryName) === 1) {
            return strtoupper($countryName);
        }

        $aliases = [
            'turkey' => 'TR',
        ];
        $normalizedCountry = $this->normalize($countryName);
        if (isset($aliases[$normalizedCountry])) {
            return $aliases[$normalizedCountry];
        }

        foreach (Countries::getNames('en') as $code => $name) {
            if ($this->normalize($name) === $normalizedCountry) {
                return $code;
            }
        }

        try {
            return Countries::getAlpha2Code($countryName);
        } catch (MissingResourceException) {
        }

        return null;
    }

    private function resolveCityName(string $municipality, ?string $targetCity): ?string
    {
        $municipality = trim($municipality);
        if ($municipality === '') {
            return null;
        }

        if ($targetCity !== null && str_contains($this->normalize($municipality), $this->normalize($targetCity))) {
            return trim($targetCity);
        }

        if (str_contains($municipality, ',')) {
            $parts = array_values(array_filter(array_map('trim', explode(',', $municipality))));

            return $parts !== [] ? end($parts) : null;
        }

        return $municipality;
    }

    private function normalizedTargetCity(?string $targetCity): ?string
    {
        if ($targetCity === null) {
            return null;
        }

        $targetCity = trim($targetCity);

        return $targetCity !== '' ? $targetCity : null;
    }

    private function isGlobalScope(string $countryName): bool
    {
        $countryName = trim($countryName);

        return $countryName === '' || strtoupper($countryName) === 'GLOBAL' || $countryName === '*';
    }

    private function validIata(string $value): ?string
    {
        $value = strtoupper(trim($value));

        return preg_match('/^[A-Z]{3}$/', $value) === 1 ? $value : null;
    }

    private function validIcao(string $value): ?string
    {
        $value = strtoupper(trim($value));

        return preg_match('/^[A-Z0-9]{4}$/', $value) === 1 ? $value : null;
    }

    private function decimalOrNull(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' && is_numeric($value) ? $value : null;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(): array
    {
        $metadataPath = $this->cachePath . '.metadata.json';
        if (!is_readable($metadataPath)) {
            return [
                'source_url' => $this->sourceUrl,
                'cache_path' => $this->cachePath,
            ];
        }

        $metadata = json_decode((string) file_get_contents($metadataPath), true);
        if (!\is_array($metadata)) {
            return [
                'source_url' => $this->sourceUrl,
                'cache_path' => $this->cachePath,
            ];
        }

        return $metadata + [
            'source_url' => $this->sourceUrl,
            'cache_path' => $this->cachePath,
        ];
    }
}
