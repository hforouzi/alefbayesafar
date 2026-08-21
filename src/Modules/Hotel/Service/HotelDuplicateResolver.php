<?php

namespace App\Modules\Hotel\Service;

use App\Modules\Destination\Entity\City;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelSourceReference;
use App\Modules\Hotel\Repository\HotelRepository;
use App\Modules\Hotel\Repository\HotelSourceReferenceRepository;
use App\Modules\Hotel\ValueObject\HotelCandidate;

final readonly class HotelDuplicateResolver
{
    public function __construct(
        private HotelRepository $hotelRepository,
        private HotelSourceReferenceRepository $sourceReferenceRepository,
        private HotelCandidateNormalizer $normalizer,
    ) {
    }

    /**
     * @return array{0: Hotel|null, 1: string|null}
     */
    public function resolve(HotelCandidate $candidate, City $city): array
    {
        if ($candidate->externalId !== null) {
            $reference = $this->sourceReferenceRepository->findOneBySourceAndExternalId($candidate->sourceIdentifier, $candidate->externalId);
            if ($reference instanceof HotelSourceReference && $reference->getHotel() instanceof Hotel) {
                return [$reference->getHotel(), 'source_external_id'];
            }
        }

        if ($candidate->externalId === null && $candidate->sourceUrl !== null) {
            $reference = $this->sourceReferenceRepository->findOneBySourceAndSourceUrl($candidate->sourceIdentifier, $candidate->sourceUrl);
            if ($reference instanceof HotelSourceReference && $reference->getHotel() instanceof Hotel) {
                return [$reference->getHotel(), 'source_url'];
            }
        }

        $website = $this->normalizer->normalizeWebsite($candidate->website);
        if ($website !== null) {
            $hotel = $this->hotelRepository->findOneByNormalizedWebsite($website);
            if ($hotel instanceof Hotel) {
                return [$hotel, 'website'];
            }
        }

        $hotel = $this->hotelRepository->findOneByCityAndSlug($city, $this->normalizer->slug($candidate->name));
        if ($hotel instanceof Hotel) {
            return [$hotel, 'city_slug'];
        }

        $hotel = $this->hotelRepository->findOneNearCoordinates($city, $candidate->latitude, $candidate->longitude);
        if ($hotel instanceof Hotel) {
            return [$hotel, 'coordinates'];
        }

        return [null, null];
    }
}
