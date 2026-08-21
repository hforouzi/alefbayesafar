<?php

namespace App\Modules\Hotel\Service;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\District;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelImage;
use App\Modules\Hotel\Entity\HotelSourceReference;
use App\Modules\Hotel\Repository\HotelRepository;
use App\Modules\Hotel\Repository\HotelSourceReferenceRepository;
use App\Modules\Hotel\ValueObject\HotelCandidate;
use Doctrine\ORM\EntityManagerInterface;

final readonly class HotelImportService
{
    private const IMAGE_LIMIT = 8;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private HotelRepository $hotelRepository,
        private HotelSourceReferenceRepository $sourceReferenceRepository,
        private HotelCandidateNormalizer $normalizer,
        private HotelDuplicateResolver $duplicateResolver,
        private HotelAmenityMapper $amenityMapper,
    ) {
    }

    /**
     * @return array{hotel: Hotel, created: bool, matchedBy: string|null, imagesAdded: int, amenitiesAdded: int}
     */
    public function import(HotelCandidate $candidate, City $city, ?District $district = null): array
    {
        if ($district instanceof District && $district->getCity() !== $city) {
            throw new \InvalidArgumentException('Hotel candidate district must belong to the selected city.');
        }

        [$hotel, $matchedBy] = $this->duplicateResolver->resolve($candidate, $city);
        $created = false;

        if (!$hotel instanceof Hotel) {
            $hotel = (new Hotel())
                ->setCity($city)
                ->setDistrict($district)
                ->setName($candidate->name)
                ->setNameFa($candidate->nameFa)
                ->setSlug($this->uniqueSlug($city, $candidate))
                ->setStars($candidate->stars)
                ->setAddress($candidate->address)
                ->setLatitude($candidate->latitude)
                ->setLongitude($candidate->longitude)
                ->setWebsite($this->normalizer->normalizeWebsite($candidate->website))
                ->setPhone($candidate->phone)
                ->setDescriptionOriginal($candidate->descriptionOriginal)
                ->setDescriptionFa($candidate->descriptionFa)
                ->setActive(true)
                ->setVerified(false);

            $this->entityManager->persist($hotel);
            $created = true;
        } else {
            $this->normalizer->applyToHotel($hotel, $candidate, false);
            if ($hotel->getDistrict() === null && $district instanceof District) {
                $hotel->setDistrict($district);
            }
        }

        $this->upsertSourceReference($hotel, $candidate, $matchedBy);
        $imagesAdded = $this->addImages($hotel, $candidate);
        $amenitiesAdded = $this->amenityMapper->apply($hotel, $candidate);
        $this->entityManager->flush();

        return [
            'hotel' => $hotel,
            'created' => $created,
            'matchedBy' => $matchedBy,
            'imagesAdded' => $imagesAdded,
            'amenitiesAdded' => $amenitiesAdded,
        ];
    }

    private function uniqueSlug(City $city, HotelCandidate $candidate): string
    {
        $base = $this->normalizer->slug($candidate->name);
        $slug = $base;
        $suffix = 1;
        while ($this->hotelRepository->findOneByCityAndSlug($city, $slug) instanceof Hotel) {
            $suffix++;
            $slug = $base . '-' . $suffix;
        }

        return $slug;
    }

    private function upsertSourceReference(Hotel $hotel, HotelCandidate $candidate, ?string $matchedBy): HotelSourceReference
    {
        $reference = null;
        if ($candidate->externalId !== null) {
            $reference = $this->sourceReferenceRepository->findOneBySourceAndExternalId($candidate->sourceIdentifier, $candidate->externalId);
        }
        if (!$reference instanceof HotelSourceReference && $candidate->externalId === null && $candidate->sourceUrl !== null) {
            $reference = $this->sourceReferenceRepository->findOneBySourceAndSourceUrl($candidate->sourceIdentifier, $candidate->sourceUrl);
        }
        if (!$reference instanceof HotelSourceReference) {
            $reference = new HotelSourceReference();
            $this->entityManager->persist($reference);
        }

        $reference
            ->setHotel($hotel)
            ->setSource($candidate->sourceIdentifier)
            ->setExternalId($candidate->externalId)
            ->setSourceUrl($candidate->sourceUrl)
            ->setSourceTitle($candidate->sourceTitle ?? $candidate->name)
            ->setChecksum($this->normalizer->checksum($candidate))
            ->setSyncStatus(HotelSourceReference::STATUS_SYNCED)
            ->setLastError(null)
            ->setMetadata($this->metadata($candidate, $matchedBy))
            ->markSeen();

        if (!$hotel->getSourceReferences()->contains($reference)) {
            $hotel->addSourceReference($reference);
        }

        return $reference;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(HotelCandidate $candidate, ?string $matchedBy): array
    {
        $rawData = $candidate->rawData;
        unset($rawData['markdown'], $rawData['html'], $rawData['content']);

        return [
            'provider' => $candidate->providerCode,
            'sourceName' => $candidate->sourceName,
            'matchedBy' => $matchedBy,
            'candidate' => [
                'name' => $candidate->name,
                'sourceUrl' => $candidate->sourceUrl,
                'sourceTitle' => $candidate->sourceTitle,
                'stars' => $candidate->stars,
                'address' => $candidate->address,
                'imageCount' => \count($candidate->images),
            ],
            'rawData' => $rawData,
        ];
    }

    private function addImages(Hotel $hotel, HotelCandidate $candidate): int
    {
        $existing = [];
        $hasPrimary = false;
        $maxPosition = -1;
        foreach ($hotel->getImages() as $image) {
            $existing[$image->getPath()] = true;
            $hasPrimary = $hasPrimary || $image->isPrimary();
            $maxPosition = max($maxPosition, $image->getPosition());
        }

        $added = 0;
        foreach (array_slice($candidate->images, 0, self::IMAGE_LIMIT) as $imageData) {
            $url = trim($imageData['url']);
            if ($url === '' || preg_match('#^https?://#i', $url) !== 1 || isset($existing[$url])) {
                continue;
            }

            $image = (new HotelImage())
                ->setHotel($hotel)
                ->setPath($url)
                ->setAlt($imageData['alt'] ?? $candidate->name)
                ->setPosition(++$maxPosition)
                ->setPrimary(!$hasPrimary);
            $hotel->addImage($image);
            $this->entityManager->persist($image);
            $existing[$url] = true;
            $hasPrimary = true;
            ++$added;
        }

        return $added;
    }
}
