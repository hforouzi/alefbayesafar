<?php

namespace App\Modules\Hotel\Service;

use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelRoomType;
use App\Modules\Hotel\Repository\HotelRoomTypeRepository;
use App\Modules\Hotel\ValueObject\HotelCandidate;
use App\Modules\Hotel\ValueObject\HotelRoomTypeCandidate;
use Doctrine\ORM\EntityManagerInterface;

final readonly class HotelRoomTypeImporter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private HotelRoomTypeRepository $roomTypeRepository,
    ) {
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function apply(Hotel $hotel, HotelCandidate $candidate): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($candidate->roomTypes as $roomTypeCandidate) {
            if (trim($roomTypeCandidate->name) === '') {
                ++$skipped;
                continue;
            }

            [$roomType, $isCreated] = $this->findOrCreate($hotel, $candidate, $roomTypeCandidate);
            $changed = $this->applyCandidate($roomType, $candidate, $roomTypeCandidate, $isCreated);

            if ($isCreated) {
                $this->entityManager->persist($roomType);
                ++$created;
            } elseif ($changed) {
                ++$updated;
            } else {
                ++$skipped;
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * @return array{0: HotelRoomType, 1: bool}
     */
    private function findOrCreate(Hotel $hotel, HotelCandidate $candidate, HotelRoomTypeCandidate $room): array
    {
        if ($room->externalId !== null && $hotel->getId() !== null) {
            $existing = $this->roomTypeRepository->findOneBySourceExternalId($hotel, $candidate->sourceIdentifier, $room->externalId);
            if ($existing instanceof HotelRoomType) {
                return [$existing, false];
            }
        }

        $existing = $hotel->getId() !== null
            ? $this->roomTypeRepository->findOneByNormalizedName($hotel, $room->name)
            : $this->findOneByNormalizedNameInMemory($hotel, $room->name);
        if ($existing instanceof HotelRoomType) {
            return [$existing, false];
        }

        $roomType = (new HotelRoomType())->setHotel($hotel)->setName($room->name)->setActive(true);
        $hotel->addRoomType($roomType);

        return [$roomType, true];
    }

    private function findOneByNormalizedNameInMemory(Hotel $hotel, string $name): ?HotelRoomType
    {
        $normalized = HotelRoomTypeRepository::normalizeName($name);
        foreach ($hotel->getRoomTypes() as $roomType) {
            if (HotelRoomTypeRepository::normalizeName($roomType->getName()) === $normalized || HotelRoomTypeRepository::normalizeName((string) $roomType->getSourceName()) === $normalized) {
                return $roomType;
            }
        }

        return null;
    }

    private function applyCandidate(HotelRoomType $roomType, HotelCandidate $candidate, HotelRoomTypeCandidate $room, bool $isCreated): bool
    {
        $changed = false;

        if ($this->setIfMissing($roomType->getSourceName(), $room->name, fn (string $value): HotelRoomType => $roomType->setSourceName($value))) {
            $changed = true;
        }
        if ($isCreated && $roomType->getName() !== $room->name) {
            $roomType->setName($room->name);
            $changed = true;
        }
        if ($this->setIfMissing($roomType->getSource(), $candidate->sourceIdentifier, fn (string $value): HotelRoomType => $roomType->setSource($value))) {
            $changed = true;
        }
        if ($this->setIfMissing($roomType->getExternalId(), $room->externalId, fn (string $value): HotelRoomType => $roomType->setExternalId($value))) {
            $changed = true;
        }
        if ($this->setIfMissing($roomType->getSourceUrl(), $room->sourceUrl ?? $candidate->sourceUrl, fn (string $value): HotelRoomType => $roomType->setSourceUrl($value))) {
            $changed = true;
        }
        if ($roomType->getMaxAdults() === null && $room->maxAdults !== null) {
            $roomType->setMaxAdults($room->maxAdults);
            $changed = true;
        }
        if ($roomType->getMaxChildren() === null && $room->maxChildren !== null) {
            $roomType->setMaxChildren($room->maxChildren);
            $changed = true;
        }
        if ($roomType->getMaxOccupancy() === null && $room->maxOccupancy !== null) {
            $roomType->setMaxOccupancy($room->maxOccupancy);
            $changed = true;
        }
        if ($this->setIfMissing($roomType->getBedConfiguration(), $room->bedConfiguration, fn (string $value): HotelRoomType => $roomType->setBedConfiguration($value))) {
            $changed = true;
        }
        if ($this->setIfMissing($roomType->getSizeSqm(), $room->sizeSqm, fn (string $value): HotelRoomType => $roomType->setSizeSqm($value))) {
            $changed = true;
        }
        if ($this->setIfMissing($roomType->getDescriptionOriginal(), $room->descriptionOriginal, fn (string $value): HotelRoomType => $roomType->setDescriptionOriginal($value))) {
            $changed = true;
        }
        if ($this->setIfMissing($roomType->getDescriptionFa(), $room->descriptionFa, fn (string $value): HotelRoomType => $roomType->setDescriptionFa($value))) {
            $changed = true;
        }

        $metadata = $roomType->getMetadata();
        $previousMetadata = $metadata;
        $metadata['lastImportedFrom'] = $candidate->sourceIdentifier;
        $metadata['provider'] = $candidate->providerCode;
        $metadata['sourceName'] = $candidate->sourceName;
        $metadata['room'] = $room->metadata;
        $roomType->setMetadata($metadata);

        return $changed || $metadata !== $previousMetadata;
    }

    /**
     * @param callable(string): HotelRoomType $setter
     */
    private function setIfMissing(?string $current, ?string $incoming, callable $setter): bool
    {
        $incoming = $incoming !== null ? trim($incoming) : '';
        if ($current !== null || $incoming === '') {
            return false;
        }

        $setter($incoming);

        return true;
    }
}
