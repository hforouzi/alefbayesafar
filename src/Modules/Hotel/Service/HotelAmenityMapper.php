<?php

namespace App\Modules\Hotel\Service;

use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Repository\HotelAmenityRepository;
use App\Modules\Hotel\ValueObject\HotelCandidate;

final readonly class HotelAmenityMapper
{
    public function __construct(
        private HotelAmenityRepository $amenityRepository,
        private HotelAmenityCatalog $catalog,
    ) {
    }

    public function apply(Hotel $hotel, HotelCandidate $candidate): int
    {
        $added = 0;
        foreach ($this->codes($candidate) as $code) {
            $amenity = $this->amenityRepository->findOneBy(['code' => $code]);
            if ($amenity !== null && !$hotel->getAmenities()->contains($amenity)) {
                $hotel->addAmenity($amenity);
                ++$added;
            }
        }

        return $added;
    }

    /**
     * @return string[]
     */
    public function codes(HotelCandidate $candidate): array
    {
        $encodedRawData = json_encode($candidate->rawData, JSON_UNESCAPED_UNICODE);
        $text = implode(' ', array_filter([
            $candidate->descriptionOriginal,
            $candidate->sourceTitle,
            \is_string($encodedRawData) ? $encodedRawData : null,
        ]));

        return $this->catalog->codesFromText($text);
    }
}
