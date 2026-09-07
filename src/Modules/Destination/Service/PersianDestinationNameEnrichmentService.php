<?php

namespace App\Modules\Destination\Service;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Entity\DestinationSourceReference;
use App\Modules\Destination\Entity\State;
use App\Modules\Destination\ValueObject\DestinationEntityType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PersianDestinationNameEnrichmentService
{
    private const ROOT_URL = 'https://download.geonames.org/export/dump/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $cacheDirectory,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * @return array{countries: int, states: int, cities: int, airports: int, skippedCurated: int, skippedMissingSource: int, files: string[], cityReport: array{checked: int, alreadyPresent: int, added: int, stillMissing: int}}
     */
    public function enrich(?string $countryIso2 = null, bool $refresh = false): array
    {
        $isTargeted = $countryIso2 !== null && trim($countryIso2) !== '';
        $iso2Values = $isTargeted
            ? [strtoupper(trim($countryIso2))]
            : $this->availableCountryCodes();

        $result = [
            'countries' => 0,
            'states' => 0,
            'cities' => 0,
            'airports' => 0,
            'skippedCurated' => 0,
            'skippedMissingSource' => 0,
            'files' => [],
        ];

        $cityRepository = $this->entityManager->getRepository(City::class);
        $citiesChecked = $cityRepository->count([]);
        $citiesMissingBefore = $cityRepository->count(['nameFa' => null]);

        foreach ($iso2Values as $iso2) {
            $alternateNames = $this->alternateNamesForCountry($iso2, $refresh, $isTargeted || $refresh);
            if ($alternateNames === []) {
                continue;
            }

            $result['files'][] = self::ROOT_URL . 'alternatenames/' . $iso2 . '.zip';
            $this->enrichType(DestinationEntityType::COUNTRY, $alternateNames, $result);
            $this->enrichType(DestinationEntityType::STATE, $alternateNames, $result);
            $this->enrichType(DestinationEntityType::CITY, $alternateNames, $result);
        }

        $curatedAdded = $this->enrichCitiesFromCuratedFallback();

        $result['skippedMissingSource'] += $this->entityManager->getRepository(Airport::class)->count(['nameFa' => null]);
        $this->entityManager->flush();

        $citiesMissingAfter = $cityRepository->count(['nameFa' => null]);
        $result['cityReport'] = [
            'checked' => $citiesChecked,
            'alreadyPresent' => $citiesChecked - $citiesMissingBefore,
            'added' => $citiesMissingBefore - $citiesMissingAfter,
            'stillMissing' => $citiesMissingAfter,
        ];
        $result['cities'] += $curatedAdded;

        return $result;
    }

    /**
     * Deterministic, network-free fallback for well-known cities that the
     * GeoNames alternate-names pass did not already cover. Never overwrites
     * an existing Persian name.
     */
    private function enrichCitiesFromCuratedFallback(): int
    {
        $added = 0;
        foreach ($this->entityManager->getRepository(City::class)->findBy(['nameFa' => null]) as $city) {
            $persianName = CuratedPersianCityNames::find($city->getCountry()?->getIso2(), $city->getName());
            if ($persianName === null) {
                continue;
            }

            $city->setNameFa($persianName);
            ++$added;
        }

        return $added;
    }

    /**
     * @param array<string, string> $alternateNames
     * @param array{countries: int, states: int, cities: int, airports: int, skippedCurated: int, skippedMissingSource: int, files: string[]} $result
     */
    private function enrichType(string $canonicalType, array $alternateNames, array &$result): void
    {
        $references = $this->entityManager->getRepository(DestinationSourceReference::class)->findBy([
            'source' => 'geonames',
            'canonicalType' => $canonicalType,
        ]);

        foreach ($references as $reference) {
            $persianName = $alternateNames[$reference->getExternalId()] ?? null;
            if ($persianName === null) {
                continue;
            }

            $canonical = $this->canonical($canonicalType, $reference->getCanonicalId());
            if (!$canonical instanceof Country && !$canonical instanceof State && !$canonical instanceof City) {
                continue;
            }

            if ($canonical->getNameFa() !== null) {
                $result['skippedCurated']++;
                continue;
            }

            $canonical->setNameFa($persianName);
            if ($canonical instanceof Country) {
                $result['countries']++;
            } elseif ($canonical instanceof State) {
                $result['states']++;
            } else {
                $result['cities']++;
            }
        }
    }

    private function canonical(string $type, int $id): object|null
    {
        return match ($type) {
            DestinationEntityType::COUNTRY => $this->entityManager->getRepository(Country::class)->find($id),
            DestinationEntityType::STATE => $this->entityManager->getRepository(State::class)->find($id),
            DestinationEntityType::CITY => $this->entityManager->getRepository(City::class)->find($id),
            default => null,
        };
    }

    /**
     * @return string[]
     */
    private function availableCountryCodes(): array
    {
        $codes = [];
        foreach ($this->entityManager->getRepository(Country::class)->findAll() as $country) {
            if ($country->getIso2() !== null) {
                $codes[] = $country->getIso2();
            }
        }

        sort($codes);

        return $codes;
    }

    /**
     * @return array<string, string>
     */
    private function alternateNamesForCountry(string $iso2, bool $refresh, bool $downloadMissing): array
    {
        $zipPath = $this->ensureDownloaded('alternatenames/' . $iso2 . '.zip', $refresh, $downloadMissing);
        if ($zipPath === null) {
            return [];
        }

        $textPath = $this->extractZip($zipPath, $iso2 . '.txt');
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

    private function ensureDownloaded(string $fileName, bool $refresh, bool $downloadMissing): ?string
    {
        $path = rtrim($this->cacheDirectory, '/\\') . DIRECTORY_SEPARATOR . $fileName;
        if (!$refresh && is_readable($path)) {
            return $path;
        }
        if (!$downloadMissing && !is_readable($path)) {
            return null;
        }

        $this->filesystem->mkdir(dirname($path));
        $temporaryPath = $path . '.tmp';

        try {
            $response = $this->httpClient->request('GET', self::ROOT_URL . $fileName, [
                'headers' => ['User-Agent' => 'AlefBayeSafarBot/0.1 (+destination persian enrichment)'],
                'timeout' => 120,
            ]);
            if ($response->getStatusCode() >= 400) {
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
}
