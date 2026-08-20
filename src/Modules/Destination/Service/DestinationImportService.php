<?php

namespace App\Modules\Destination\Service;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Entity\DestinationImportRun;
use App\Modules\Destination\Entity\DestinationSourceReference;
use App\Modules\Destination\Entity\District;
use App\Modules\Destination\Entity\State;
use App\Modules\Destination\Provider\DestinationProviderRegistry;
use App\Modules\Destination\Repository\AirportRepository;
use App\Modules\Destination\Repository\CityRepository;
use App\Modules\Destination\Repository\CountryRepository;
use App\Modules\Destination\Repository\DestinationSourceReferenceRepository;
use App\Modules\Destination\Repository\DistrictRepository;
use App\Modules\Destination\Repository\StateRepository;
use App\Modules\Destination\ValueObject\DestinationCandidate;
use App\Modules\Destination\ValueObject\DestinationEntityType;
use App\Modules\Destination\ValueObject\DestinationImportRequest;
use App\Modules\Destination\ValueObject\DestinationImportSummary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;

final class DestinationImportService
{
    private bool $detachHighVolumeCities = false;
    private bool $detachHighVolumeAirports = false;

    public function __construct(
        private readonly DestinationProviderRegistry $providerRegistry,
        private readonly DestinationNormalizer $normalizer,
        private readonly EntityManagerInterface $entityManager,
        private readonly CountryRepository $countryRepository,
        private readonly StateRepository $stateRepository,
        private readonly CityRepository $cityRepository,
        private readonly DistrictRepository $districtRepository,
        private readonly AirportRepository $airportRepository,
        private readonly DestinationSourceReferenceRepository $sourceReferenceRepository,
        private readonly ?DebugDataHolder $debugDataHolder = null,
    ) {
    }

    public function import(DestinationImportRequest $request): DestinationImportSummary
    {
        $this->detachHighVolumeCities = $request->targetType === DestinationEntityType::CITY
            && $request->providerCodes === ['geonames'];
        $this->detachHighVolumeAirports = $request->targetType === DestinationEntityType::AIRPORT
            && $request->providerCodes === ['ourairports']
            && $request->cityName === null;

        $run = (new DestinationImportRun())
            ->setTargetType($request->targetType)
            ->setCountryName($request->countryName)
            ->setCityName($request->cityName)
            ->setProviders($request->providerCodes)
            ->setRefresh($request->refresh);
        $this->entityManager->persist($run);

        $summary = new DestinationImportSummary($run);
        $providers = $this->providerRegistry->selected($request->providerCodes);

        if ($providers === []) {
            $summary->addError('No enabled provider was selected.');
        }

        foreach ($providers as $provider) {
            $providerResult = $provider->discover($request);
            if (!$providerResult->success) {
                foreach ($providerResult->errors as $error) {
                    $summary->addError(sprintf('%s: %s', $provider->getLabel(), $error));
                }
                continue;
            }

            foreach ($providerResult->candidates as $candidate) {
                $summary->found++;
                $this->importCandidate($candidate, $summary);
            }
        }

        $run
            ->setFoundCount($summary->found)
            ->setCreatedCount($summary->created)
            ->setUpdatedCount($summary->updated)
            ->setUnchangedCount($summary->unchanged)
            ->setSkippedCount($summary->skipped)
            ->setDuplicateCount($summary->duplicates)
            ->setFailedCount($summary->failed)
            ->setErrors($summary->errors)
            ->setSummary(['items' => $summary->items, 'itemsSuppressed' => $summary->itemsSuppressed])
            ->finish($summary->status());

        $this->entityManager->flush();
        $this->detachHighVolumeCities = false;
        $this->detachHighVolumeAirports = false;

        return $summary;
    }

    private function importCandidate(DestinationCandidate $candidate, DestinationImportSummary $summary): void
    {
        $checksum = $this->normalizer->checksum($this->fingerprintData($candidate) + [
            'name' => $candidate->name,
            'type' => $candidate->type,
            'external_id' => $candidate->externalId,
            'source_url' => $candidate->sourceUrl,
        ]);

        $existingReference = $candidate->externalId !== null
            ? $this->sourceReferenceRepository->findForExternalId($candidate->provider, $candidate->type, $candidate->externalId)
            : null;

        $canonical = $existingReference instanceof DestinationSourceReference
            ? $this->findCanonical($existingReference->getCanonicalType(), $existingReference->getCanonicalId())
            : null;

        $matchedBy = $canonical !== null ? 'source_reference' : null;
        if ($canonical instanceof Airport && $this->airportIataConflicts($canonical, $candidate)) {
            $canonical = null;
            $matchedBy = null;
        }

        if ($canonical === null) {
            [$canonical, $matchedBy] = $this->matchCanonical($candidate);
        }

        if ($canonical === null) {
            $canonical = $this->createCanonical($candidate);
            if ($canonical === null) {
                $summary->skipped++;
                $summary->addItem($this->item($candidate, 'skipped', null, 'Missing required parent canonical record.'));

                return;
            }

            $this->entityManager->persist($canonical);
            $this->entityManager->flush();
            $summary->created++;
            $status = 'created';
        } else {
            $changed = $this->applySafeUpdates($canonical, $candidate, $matchedBy === 'source_reference');
            $status = $changed ? 'updated' : 'unchanged';
            if ($changed) {
                $summary->updated++;
            } else {
                $summary->unchanged++;
            }
        }

        $sourceReference = $this->upsertSourceReference($candidate, $canonical, $checksum);
        $this->entityManager->flush();
        if ($this->detachHighVolumeCities && $canonical instanceof City) {
            $this->entityManager->detach($canonical);
        }
        if ($this->detachHighVolumeAirports && $canonical instanceof Airport) {
            $this->entityManager->detach($canonical);
        }
        if ($sourceReference instanceof DestinationSourceReference) {
            $this->entityManager->detach($sourceReference);
        }
        if ($this->detachHighVolumeCities || $this->detachHighVolumeAirports) {
            $this->debugDataHolder?->reset();
        }

        $summary->addItem($this->item($candidate, $status, $this->canonicalId($canonical), $matchedBy));
    }

    /**
     * @return array<string, mixed>
     */
    private function fingerprintData(DestinationCandidate $candidate): array
    {
        $data = $candidate->rawData;
        foreach (['success', 'downloadedAt', 'sourceUrl', 'fileSize', 'checksum', 'cache_path', 'source_url'] as $volatileKey) {
            unset($data[$volatileKey]);
        }

        return $data;
    }

    /**
     * @return array{0: object|null, 1: string|null}
     */
    private function matchCanonical(DestinationCandidate $candidate): array
    {
        return match ($candidate->type) {
            DestinationEntityType::COUNTRY => [$this->matchCountry($candidate), 'country_match'],
            DestinationEntityType::STATE => [$this->matchState($candidate), 'state_match'],
            DestinationEntityType::CITY => [$this->matchCity($candidate), 'city_match'],
            DestinationEntityType::DISTRICT => [$this->matchDistrict($candidate), 'district_match'],
            DestinationEntityType::AIRPORT => [$this->matchAirport($candidate), 'airport_match'],
            default => [null, null],
        };
    }

    private function matchState(DestinationCandidate $candidate): ?State
    {
        $country = $this->findCountryForCandidate($candidate);
        if (!$country instanceof Country) {
            return null;
        }

        if ($candidate->admin1Code !== null) {
            $state = $this->stateRepository->findOneByCountryAndCode($country, $candidate->admin1Code);
            if ($state instanceof State) {
                return $state;
            }
        }

        $normalized = $this->normalizer->normalizeName($candidate->name);
        foreach ($this->stateRepository->findBy(['country' => $country]) as $state) {
            if ($this->normalizer->normalizeName($state->getName()) === $normalized) {
                return $state;
            }
        }

        return null;
    }

    private function matchCountry(DestinationCandidate $candidate): ?Country
    {
        if ($candidate->iso2 !== null) {
            $country = $this->countryRepository->findOneBy(['iso2' => strtoupper($candidate->iso2)]);
            if ($country instanceof Country) {
                return $country;
            }
        }

        if ($candidate->iso3 !== null) {
            $country = $this->countryRepository->findOneBy(['iso3' => strtoupper($candidate->iso3)]);
            if ($country instanceof Country) {
                return $country;
            }
        }

        $normalized = $this->normalizer->normalizeName($candidate->name);
        foreach ($this->countryRepository->findAll() as $country) {
            if ($this->normalizer->normalizeName($country->getName()) === $normalized) {
                return $country;
            }
        }

        return null;
    }

    private function matchCity(DestinationCandidate $candidate): ?City
    {
        $country = $this->findCountryForCandidate($candidate);
        if (!$country instanceof Country) {
            return null;
        }

        return $this->findCityByName($country, $candidate->name, $this->findStateForCandidate($candidate));
    }

    private function matchDistrict(DestinationCandidate $candidate): ?District
    {
        $city = $this->findCityForCandidate($candidate);
        if (!$city instanceof City) {
            return null;
        }

        $normalized = $this->normalizer->normalizeDistrictName($candidate->name);
        foreach ($this->districtRepository->findBy(['city' => $city]) as $district) {
            if ($this->normalizer->normalizeDistrictName($district->getName()) === $normalized) {
                return $district;
            }
        }

        return null;
    }

    private function matchAirport(DestinationCandidate $candidate): ?Airport
    {
        if ($candidate->icaoCode !== null) {
            $airport = $this->airportRepository->findOneBy(['icaoCode' => strtoupper($candidate->icaoCode)]);
            if ($airport instanceof Airport) {
                return $airport;
            }
        }

        if ($candidate->iataCode !== null) {
            $airport = $this->airportRepository->findOneBy(['iataCode' => strtoupper($candidate->iataCode)]);
            if ($airport instanceof Airport) {
                return $airport;
            }
        }

        $city = $this->findCityForCandidate($candidate);
        if (!$city instanceof City) {
            return null;
        }

        $normalized = $this->normalizer->normalizeName($candidate->name);
        foreach ($this->airportRepository->findBy(['city' => $city]) as $airport) {
            if (!$this->airportCodesAreCompatible($airport, $candidate)) {
                continue;
            }

            if ($this->normalizer->normalizeName($airport->getName()) === $normalized) {
                return $airport;
            }
        }

        return null;
    }

    private function createCanonical(DestinationCandidate $candidate): Country|State|City|District|Airport|null
    {
        return match ($candidate->type) {
            DestinationEntityType::COUNTRY => (new Country())
                ->setName($candidate->name)
                ->setNameFa($candidate->nameFa)
                ->setIso2($candidate->iso2)
                ->setIso3($candidate->iso3),
            DestinationEntityType::STATE => $this->createState($candidate),
            DestinationEntityType::CITY => $this->createCity($candidate),
            DestinationEntityType::DISTRICT => $this->createDistrict($candidate),
            DestinationEntityType::AIRPORT => $this->createAirport($candidate),
            default => null,
        };
    }

    private function createState(DestinationCandidate $candidate): ?State
    {
        $country = $this->findCountryForCandidate($candidate);
        if (!$country instanceof Country || $candidate->admin1Code === null) {
            return null;
        }

        return (new State())
            ->setCountry($country)
            ->setName($candidate->name)
            ->setNameFa($candidate->nameFa)
            ->setCode($candidate->admin1Code)
            ->setSlug($this->normalizer->slug($candidate->name));
    }

    private function createCity(DestinationCandidate $candidate): ?City
    {
        $country = $this->findCountryForCandidate($candidate);
        if (!$country instanceof Country) {
            return null;
        }

        return (new City())
            ->setCountry($country)
            ->setState($this->findStateForCandidate($candidate))
            ->setName($candidate->name)
            ->setNameFa($candidate->nameFa)
            ->setSlug($this->normalizer->slug($candidate->name));
    }

    private function createDistrict(DestinationCandidate $candidate): ?District
    {
        $city = $this->findCityForCandidate($candidate);
        if (!$city instanceof City) {
            return null;
        }

        return (new District())
            ->setCity($city)
            ->setName($candidate->name)
            ->setNameFa($candidate->nameFa)
            ->setSlug($this->normalizer->slug($this->normalizer->normalizeDistrictName($candidate->name)));
    }

    private function createAirport(DestinationCandidate $candidate): ?Airport
    {
        $city = $this->findCityForCandidate($candidate);
        if (!$city instanceof City) {
            return null;
        }

        return (new Airport())
            ->setCity($city)
            ->setName($candidate->name)
            ->setNameFa($candidate->nameFa)
            ->setIataCode($candidate->iataCode)
            ->setIcaoCode($candidate->icaoCode)
            ->setLatitude($candidate->latitude)
            ->setLongitude($candidate->longitude);
    }

    private function airportCodesAreCompatible(Airport $airport, DestinationCandidate $candidate): bool
    {
        if ($candidate->iataCode !== null && $airport->getIataCode() !== null && strtoupper($airport->getIataCode()) !== strtoupper($candidate->iataCode)) {
            return false;
        }

        if ($candidate->icaoCode !== null && $airport->getIcaoCode() !== null && strtoupper($airport->getIcaoCode()) !== strtoupper($candidate->icaoCode)) {
            return false;
        }

        return true;
    }

    private function airportIataConflicts(Airport $airport, DestinationCandidate $candidate): bool
    {
        return $candidate->iataCode !== null
            && $airport->getIataCode() !== null
            && strtoupper($airport->getIataCode()) !== strtoupper($candidate->iataCode);
    }

    private function applySafeUpdates(object $canonical, DestinationCandidate $candidate, bool $matchedSourceReference = false): bool
    {
        $changed = false;

        if ($canonical instanceof Country) {
            if ($this->setIfMissing($canonical->getNameFa(), $candidate->nameFa, fn (string $value): Country => $canonical->setNameFa($value))) {
                $changed = true;
            }
            if ($this->setIfMissing($canonical->getIso2(), $candidate->iso2, fn (string $value): Country => $canonical->setIso2($value))) {
                $changed = true;
            }
            if ($this->setIfMissing($canonical->getIso3(), $candidate->iso3, fn (string $value): Country => $canonical->setIso3($value))) {
                $changed = true;
            }
        }

        if ($canonical instanceof State || $canonical instanceof City || $canonical instanceof District || $canonical instanceof Airport) {
            if ($this->setIfMissing($canonical->getNameFa(), $candidate->nameFa, fn (string $value): object => $canonical->setNameFa($value))) {
                $changed = true;
            }
        }

        if ($canonical instanceof State) {
            if ($this->setIfMissing($canonical->getCode(), $candidate->admin1Code, fn (string $value): State => $canonical->setCode($value))) {
                $changed = true;
            }
        }

        if ($canonical instanceof City && !$canonical->getState() instanceof State) {
            $state = $this->findStateForCandidate($candidate);
            if ($state instanceof State) {
                $canonical->setState($state);
                $changed = true;
            }
        }

        if ($canonical instanceof Airport) {
            if ($this->setIfMissingOrSourceOwned($canonical->getIataCode(), $candidate->iataCode, $matchedSourceReference, fn (string $value): Airport => $canonical->setIataCode($value))) {
                $changed = true;
            }
            if ($this->setIfMissingOrSourceOwned($canonical->getIcaoCode(), $candidate->icaoCode, $matchedSourceReference, fn (string $value): Airport => $canonical->setIcaoCode($value))) {
                $changed = true;
            }
            if ($this->setDecimalIfMissingOrSourceOwned($canonical->getLatitude(), $candidate->latitude, $matchedSourceReference, fn (string $value): Airport => $canonical->setLatitude($value))) {
                $changed = true;
            }
            if ($this->setDecimalIfMissingOrSourceOwned($canonical->getLongitude(), $candidate->longitude, $matchedSourceReference, fn (string $value): Airport => $canonical->setLongitude($value))) {
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * @param callable(string): object $setter
     */
    private function setIfMissing(null|string $current, null|string $incoming, callable $setter): bool
    {
        if ($current !== null || $incoming === null || trim($incoming) === '') {
            return false;
        }

        $setter($incoming);

        return true;
    }

    /**
     * @param callable(string): object $setter
     */
    private function setIfMissingOrSourceOwned(null|string $current, null|string $incoming, bool $sourceOwned, callable $setter): bool
    {
        if ($incoming === null || trim($incoming) === '') {
            return false;
        }

        if ($current !== null && (!$sourceOwned || trim($current) === trim($incoming))) {
            return false;
        }

        $setter($incoming);

        return true;
    }

    /**
     * @param callable(string): object $setter
     */
    private function setDecimalIfMissingOrSourceOwned(null|string $current, null|string $incoming, bool $sourceOwned, callable $setter): bool
    {
        if ($incoming === null || trim($incoming) === '') {
            return false;
        }

        if ($current !== null) {
            if (!$sourceOwned) {
                return false;
            }

            if (is_numeric($current) && is_numeric($incoming) && abs((float) $current - (float) $incoming) < 0.0000001) {
                return false;
            }

            if (trim($current) === trim($incoming)) {
                return false;
            }
        }

        $setter($incoming);

        return true;
    }

    private function upsertSourceReference(DestinationCandidate $candidate, object $canonical, string $checksum): ?DestinationSourceReference
    {
        $canonicalId = $this->canonicalId($canonical);
        if (!\is_int($canonicalId)) {
            return null;
        }

        $externalId = $candidate->externalId ?? hash('sha256', $candidate->provider . ':' . $candidate->type . ':' . $canonicalId . ':' . $candidate->name);
        $reference = $this->sourceReferenceRepository->findForExternalId($candidate->provider, $candidate->type, $externalId);

        if (!$reference instanceof DestinationSourceReference) {
            $reference = new DestinationSourceReference();
            $reference->setExternalId($externalId);
            $this->entityManager->persist($reference);
        }

        $reference
            ->setSource($candidate->provider)
            ->setCanonicalType($candidate->type)
            ->setCanonicalId($canonicalId)
            ->setSourceUrl($candidate->sourceUrl)
            ->setSourceTitle($candidate->sourceTitle ?? $candidate->name)
            ->setNormalizedName($candidate->type === DestinationEntityType::DISTRICT ? $this->normalizer->normalizeDistrictName($candidate->name) : $this->normalizer->normalizeName($candidate->name))
            ->setChecksum($checksum)
            ->setDataUpdatedAt($candidate->dataUpdatedAt)
            ->setSyncStatus(DestinationSourceReference::STATUS_SYNCED)
            ->setLastError(null)
            ->setMetadata($candidate->rawData)
            ->markSeen();

        return $reference;
    }

    private function findCountryForCandidate(DestinationCandidate $candidate): ?Country
    {
        $countryName = $candidate->countryName ?? $candidate->name;
        if (preg_match('/^[A-Za-z]{2}$/', $countryName) === 1) {
            $country = $this->countryRepository->findOneBy(['iso2' => strtoupper($countryName)]);
            if ($country instanceof Country) {
                return $country;
            }
        }

        $normalized = $this->normalizer->normalizeName($countryName);

        foreach ($this->countryRepository->findAll() as $country) {
            if ($this->normalizer->normalizeName($country->getName()) === $normalized) {
                return $country;
            }
        }

        return null;
    }

    private function findCityForCandidate(DestinationCandidate $candidate): ?City
    {
        if ($candidate->cityName === null) {
            return null;
        }

        $country = $this->findCountryForCandidate($candidate);
        if (!$country instanceof Country) {
            return null;
        }

        return $this->findCityByName($country, $candidate->cityName, $this->findStateForCandidate($candidate));
    }

    private function findCityByName(Country $country, string $name, ?State $state): ?City
    {
        $slug = $this->normalizer->slug($name);

        if ($state instanceof State) {
            $city = $this->cityRepository->findOneByCountryStateAndName($country, $state, $name);
            if ($city instanceof City) {
                return $city;
            }

            $city = $this->cityRepository->findOneByCountryStateAndSlug($country, $state, $slug);
            if ($city instanceof City) {
                return $city;
            }
        }

        $city = $this->cityRepository->findOneByCountryStateAndName($country, null, $name);
        if ($city instanceof City) {
            return $city;
        }

        $city = $this->cityRepository->findOneByCountryStateAndSlug($country, null, $slug);
        if ($city instanceof City) {
            return $city;
        }

        $city = $this->cityRepository->findOneUnambiguousByCountryAndName($country, $name);
        if ($city instanceof City) {
            return $city;
        }

        return $this->cityRepository->findOneUnambiguousByCountryAndSlug($country, $slug);
    }

    private function findStateForCandidate(DestinationCandidate $candidate): ?State
    {
        $country = $this->findCountryForCandidate($candidate);
        if (!$country instanceof Country) {
            return null;
        }

        if ($candidate->admin1Code !== null) {
            $state = $this->stateRepository->findOneByCountryAndCode($country, $candidate->admin1Code);
            if ($state instanceof State) {
                return $state;
            }
        }

        if ($candidate->stateName === null) {
            return null;
        }

        $normalized = $this->normalizer->normalizeName($candidate->stateName);
        foreach ($this->stateRepository->findBy(['country' => $country]) as $state) {
            if ($this->normalizer->normalizeName($state->getName()) === $normalized) {
                return $state;
            }
        }

        return null;
    }

    private function findCanonical(string $type, int $id): object|null
    {
        return match ($type) {
            DestinationEntityType::COUNTRY => $this->countryRepository->find($id),
            DestinationEntityType::STATE => $this->stateRepository->find($id),
            DestinationEntityType::CITY => $this->cityRepository->find($id),
            DestinationEntityType::DISTRICT => $this->districtRepository->find($id),
            DestinationEntityType::AIRPORT => $this->airportRepository->find($id),
            default => null,
        };
    }

    private function canonicalId(object $canonical): ?int
    {
        return match (true) {
            $canonical instanceof Country => $canonical->getId(),
            $canonical instanceof State => $canonical->getId(),
            $canonical instanceof City => $canonical->getId(),
            $canonical instanceof District => $canonical->getId(),
            $canonical instanceof Airport => $canonical->getId(),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function item(DestinationCandidate $candidate, string $status, ?int $canonicalId, ?string $note): array
    {
        return [
            'provider' => $candidate->provider,
            'type' => $candidate->type,
            'name' => $candidate->name,
            'status' => $status,
            'canonicalId' => $canonicalId,
            'note' => $note,
        ];
    }
}
