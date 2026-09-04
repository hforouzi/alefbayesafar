<?php

namespace App\Modules\Tour\Service;

use App\Modules\Destination\Entity\City;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelRoomType;
use App\Modules\Hotel\Repository\HotelRepository;
use App\Modules\Hotel\Repository\HotelRoomTypeRepository;
use App\Modules\Hotel\Service\HotelSourceIdentityMatcher;
use App\Modules\Tour\Entity\ExternalTourOffer;
use App\Modules\Tour\Enum\ExternalTourOfferSearchStatus;
use App\Modules\Tour\Repository\ExternalTourOfferRepository;
use App\Modules\Tour\ValueObject\ExternalTourOfferCandidate;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchRequest;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchResult;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchSummary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ExternalTourOfferStoreService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ExternalTourOfferRepository $offerRepository,
        private HotelRepository $hotelRepository,
        private HotelRoomTypeRepository $roomTypeRepository,
        private HotelSourceIdentityMatcher $hotelMatcher,
        #[Autowire('%env(default:tour.external_offer_ttl_minutes:TOUR_EXTERNAL_OFFER_TTL_MINUTES)%')]
        private ?string $ttlMinutes = '360',
    ) {
    }

    public function storeSummary(ExternalTourOfferSearchRequest $request, ExternalTourOfferSearchSummary $summary, ?\DateTimeImmutable $fetchedAt = null): int
    {
        $stored = 0;
        foreach ($summary->getResults() as $result) {
            $stored += $this->storeResult($request, $result, $fetchedAt);
        }

        return $stored;
    }

    public function storeResult(ExternalTourOfferSearchRequest $request, ExternalTourOfferSearchResult $result, ?\DateTimeImmutable $fetchedAt = null): int
    {
        if ($result->status === ExternalTourOfferSearchStatus::NO_DATA || $result->status === ExternalTourOfferSearchStatus::PROVIDER_ERROR || $result->status === ExternalTourOfferSearchStatus::SKIPPED) {
            return 0;
        }

        $fetchedAt ??= new \DateTimeImmutable();
        $stored = 0;
        $this->entityManager->wrapInTransaction(function () use ($request, $result, $fetchedAt, &$stored): void {
            $this->offerRepository->deleteExternalSnapshot($result->source, $request);

            if ($result->status === ExternalTourOfferSearchStatus::NO_RESULTS) {
                return;
            }

            foreach ($this->deduplicate($result->candidates) as $candidate) {
                $this->entityManager->persist($this->offerFromCandidate($request, $result, $candidate, $fetchedAt));
                ++$stored;
            }
        });

        return $stored;
    }

    private function offerFromCandidate(ExternalTourOfferSearchRequest $request, ExternalTourOfferSearchResult $result, ExternalTourOfferCandidate $candidate, \DateTimeImmutable $fetchedAt): ExternalTourOffer
    {
        $hotel = $this->safeHotel($request->destinationCity, $candidate);
        $roomType = $hotel instanceof Hotel ? $this->safeRoomType($hotel, $candidate) : null;

        return (new ExternalTourOffer())
            ->setSearchSource($result->source)
            ->setProviderCode($candidate->providerCode)
            ->setExternalOfferId($candidate->externalOfferId)
            ->setTitle($candidate->title)
            ->setOriginAirport($request->originAirport)
            ->setOriginText($candidate->originText ?? $request->originAirport?->getIataCode())
            ->setDestinationCity($request->destinationCity)
            ->setDestinationText($candidate->destinationText)
            ->setDepartureDate($candidate->departureDate)
            ->setReturnDate($candidate->returnDate)
            ->setValidFrom($candidate->validFrom)
            ->setValidTo($candidate->validTo)
            ->setNights($candidate->nights)
            ->setDays($candidate->days)
            ->setAdults($candidate->adults)
            ->setChildren($candidate->children)
            ->setInfants($candidate->infants)
            ->setChildrenAges($candidate->childrenAges)
            ->setHotel($hotel)
            ->setHotelName($candidate->hotelName)
            ->setHotelRoomType($roomType)
            ->setBoardType($candidate->boardType)
            ->setFlightSummary($candidate->flightSummary)
            ->setInclusions($candidate->inclusions)
            ->setExclusions($candidate->exclusions)
            ->setCurrency($candidate->currency)
            ->setTotalPrice($candidate->totalPrice)
            ->setBookingUrl($candidate->bookingUrl)
            ->setAvailabilityStatus($candidate->availabilityStatus)
            ->setFetchedAt($fetchedAt)
            ->setExpiresAt($fetchedAt->modify('+' . $this->ttl() . ' minutes'))
            ->setMetadata($candidate->metadata + [
                'sourceIdentifier' => $candidate->sourceIdentifier,
                'sourceName' => $candidate->sourceName,
                'searchContext' => $request->context(),
                'searchContextHash' => $request->contextHash(),
                'ttlMinutes' => $this->ttl(),
                'providerRoomName' => $candidate->roomName,
                'rooms' => $request->rooms,
            ]);
    }

    private function safeHotel(City $city, ExternalTourOfferCandidate $candidate): ?Hotel
    {
        if ($candidate->hotelName === null) {
            return null;
        }

        $hotels = $this->hotelRepository->createQueryBuilder('hotel')
            ->andWhere('hotel.city = :city')
            ->andWhere('hotel.name LIKE :query OR hotel.nameFa LIKE :query')
            ->setParameter('city', $city)
            ->setParameter('query', '%' . $candidate->hotelName . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        foreach ($hotels as $hotel) {
            if (!$hotel instanceof Hotel) {
                continue;
            }
            if ($this->hotelMatcher->matches($hotel, $candidate->hotelName, $candidate->bookingUrl, $candidate->metadata)) {
                return $hotel;
            }
        }

        return null;
    }

    private function safeRoomType(Hotel $hotel, ExternalTourOfferCandidate $candidate): ?HotelRoomType
    {
        return $candidate->roomName !== null ? $this->roomTypeRepository->findOneByNormalizedName($hotel, $candidate->roomName) : null;
    }

    /**
     * @param ExternalTourOfferCandidate[] $candidates
     *
     * @return ExternalTourOfferCandidate[]
     */
    private function deduplicate(array $candidates): array
    {
        $deduplicated = [];
        foreach ($candidates as $candidate) {
            $deduplicated[$candidate->externalOfferId ?? implode('|', [$candidate->title, $candidate->currency, $candidate->totalPrice, $candidate->bookingUrl ?? ''])] = $candidate;
        }

        return array_values($deduplicated);
    }

    private function ttl(): int
    {
        $ttl = filter_var($this->ttlMinutes, FILTER_VALIDATE_INT);

        return \is_int($ttl) && $ttl > 0 ? $ttl : 360;
    }
}
