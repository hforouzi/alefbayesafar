<?php

namespace App\Modules\Hotel\Service;

use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelOffer;
use App\Modules\Hotel\Enum\HotelOfferSearchStatus;
use App\Modules\Hotel\Repository\HotelOfferRepository;
use App\Modules\Hotel\ValueObject\HotelOfferCandidate;
use App\Modules\Hotel\ValueObject\HotelOfferSearchRequest;
use App\Modules\Hotel\ValueObject\HotelOfferSearchResult;
use App\Modules\Hotel\ValueObject\HotelOfferSearchSummary;
use Doctrine\ORM\EntityManagerInterface;

final readonly class HotelOfferStoreService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private HotelOfferRepository $offerRepository,
    ) {
    }

    public function storeSummary(Hotel $hotel, HotelOfferSearchRequest $request, HotelOfferSearchSummary $summary, ?\DateTimeImmutable $fetchedAt = null): int
    {
        $stored = 0;
        foreach ($summary->getResults() as $result) {
            $stored += $this->storeResult($hotel, $request, $result, $fetchedAt);
        }

        return $stored;
    }

    public function storeResult(Hotel $hotel, HotelOfferSearchRequest $request, HotelOfferSearchResult $result, ?\DateTimeImmutable $fetchedAt = null): int
    {
        if ($result->status !== HotelOfferSearchStatus::OFFERS_FOUND) {
            return 0;
        }

        $fetchedAt ??= new \DateTimeImmutable();
        $stored = 0;

        $this->entityManager->wrapInTransaction(function () use ($hotel, $request, $result, $fetchedAt, &$stored): void {
            $this->offerRepository->deleteForSearchContext($hotel, $result->source, $request);

            foreach ($this->deduplicate($result->candidates) as $candidate) {
                $this->entityManager->persist($this->offerFromCandidate($hotel, $request, $result, $candidate, $fetchedAt));
                ++$stored;
            }
        });

        return $stored;
    }

    private function offerFromCandidate(Hotel $hotel, HotelOfferSearchRequest $request, HotelOfferSearchResult $result, HotelOfferCandidate $candidate, \DateTimeImmutable $fetchedAt): HotelOffer
    {
        return (new HotelOffer())
            ->setHotel($hotel)
            ->setSearchSource($result->source)
            ->setProviderCode($candidate->providerCode)
            ->setExternalOfferId($candidate->externalOfferId)
            ->setCheckIn($request->checkIn)
            ->setCheckOut($request->checkOut)
            ->setAdults($request->adults)
            ->setChildren($request->children)
            ->setChildrenAges($request->childrenAges)
            ->setRoomName($candidate->roomName)
            ->setBoardType($candidate->boardType)
            ->setCurrency($candidate->currency)
            ->setTotalPrice($candidate->totalPrice)
            ->setBookingUrl($candidate->bookingUrl)
            ->setAvailabilityStatus($candidate->availabilityStatus)
            ->setFetchedAt($fetchedAt)
            ->setMetadata($candidate->metadata);
    }

    /**
     * @param HotelOfferCandidate[] $candidates
     *
     * @return HotelOfferCandidate[]
     */
    private function deduplicate(array $candidates): array
    {
        $deduplicated = [];
        foreach ($candidates as $candidate) {
            $deduplicated[$this->candidateKey($candidate)] = $candidate;
        }

        return array_values($deduplicated);
    }

    private function candidateKey(HotelOfferCandidate $candidate): string
    {
        if ($candidate->externalOfferId !== null) {
            return 'id:' . $candidate->externalOfferId;
        }

        return implode('|', [
            $candidate->providerCode,
            $candidate->roomName ?? '',
            $candidate->boardType ?? '',
            $candidate->currency,
            $candidate->totalPrice,
            $candidate->bookingUrl ?? '',
            $candidate->availabilityStatus ?? '',
        ]);
    }
}
