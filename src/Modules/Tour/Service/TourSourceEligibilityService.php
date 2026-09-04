<?php

namespace App\Modules\Tour\Service;

use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\Tour\Enum\TourSourceEligibilityStatus;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchRequest;
use App\Modules\Tour\ValueObject\TourSourceEligibilityResult;

final readonly class TourSourceEligibilityService
{
    public function evaluate(SearchSource $source, ExternalTourOfferSearchRequest $request): TourSourceEligibilityResult
    {
        $config = $source->getConfig();
        $known = false;

        $originCityIds = $this->intList($config['supportedOriginCityIds'] ?? []);
        if ($originCityIds !== []) {
            $known = true;
            $originCity = $request->originAirport?->getCity()?->getId();
            if ($originCity === null || !\in_array($originCity, $originCityIds, true)) {
                return new TourSourceEligibilityResult(TourSourceEligibilityStatus::INELIGIBLE, ['ORIGIN_NOT_SUPPORTED']);
            }
        }

        $destinationCityIds = $this->intList($config['supportedDestinationCityIds'] ?? []);
        if ($destinationCityIds !== []) {
            $known = true;
            $destinationCity = $request->destinationCity->getId();
            if ($destinationCity === null || !\in_array($destinationCity, $destinationCityIds, true)) {
                return new TourSourceEligibilityResult(TourSourceEligibilityStatus::INELIGIBLE, ['DESTINATION_NOT_SUPPORTED']);
            }
        }

        return new TourSourceEligibilityResult($known ? TourSourceEligibilityStatus::ELIGIBLE : TourSourceEligibilityStatus::UNKNOWN);
    }

    /**
     * @return int[]
     */
    private function intList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            if (\is_int($item) || (\is_string($item) && ctype_digit($item))) {
                $ids[] = (int) $item;
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }
}
