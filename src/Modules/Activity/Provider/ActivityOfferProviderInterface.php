<?php

namespace App\Modules\Activity\Provider;

use App\Modules\Activity\ValueObject\ExternalActivityOfferCandidate;
use App\Modules\Destination\Entity\City;
use App\Modules\SearchSource\Entity\SearchSource;

/**
 * Boundary interface for a future external Activity offer source.
 *
 * SearchSource already exposes SearchSource::CAPABILITY_ACTIVITY. No concrete
 * implementation is registered in Phase 9 — this interface exists only so a
 * future provider can be added later without changing the resolver contract.
 */
interface ActivityOfferProviderInterface
{
    public function supports(SearchSource $source): bool;

    /**
     * @return ExternalActivityOfferCandidate[]
     */
    public function search(SearchSource $source, City $destinationCity, \DateTimeImmutable $date, int $adults, int $children, int $infants): array;
}
