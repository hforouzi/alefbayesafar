<?php

namespace App\Modules\Flight\Service;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Repository\AirportRepository;
use App\Modules\Flight\Entity\Airline;
use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Entity\FlightOfferLeg;
use App\Modules\Flight\Enum\FlightOfferSearchStatus;
use App\Modules\Flight\Enum\FlightPriceSourceType;
use App\Modules\Flight\Enum\FlightPricingMode;
use App\Modules\Flight\Repository\AirlineRepository;
use App\Modules\Flight\Repository\FlightOfferRepository;
use App\Modules\Flight\ValueObject\FlightOfferCandidate;
use App\Modules\Flight\ValueObject\FlightOfferLegCandidate;
use App\Modules\Flight\ValueObject\FlightOfferSearchRequest;
use App\Modules\Flight\ValueObject\FlightOfferSearchResult;
use App\Modules\Flight\ValueObject\FlightOfferSearchSummary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class FlightOfferStoreService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FlightOfferRepository $offerRepository,
        private AirportRepository $airportRepository,
        private AirlineRepository $airlineRepository,
        #[Autowire('%env(default:flight.external_offer_ttl_minutes:FLIGHT_EXTERNAL_OFFER_TTL_MINUTES)%')]
        private ?string $ttlMinutes = '20',
    ) {
    }

    public function storeSummary(FlightOfferSearchRequest $request, FlightOfferSearchSummary $summary, ?\DateTimeImmutable $fetchedAt = null): int
    {
        $stored = 0;
        foreach ($summary->getResults() as $result) {
            $stored += $this->storeResult($request, $result, $fetchedAt);
        }

        return $stored;
    }

    public function storeResult(FlightOfferSearchRequest $request, FlightOfferSearchResult $result, ?\DateTimeImmutable $fetchedAt = null): int
    {
        if ($result->status === FlightOfferSearchStatus::NO_DATA || $result->status === FlightOfferSearchStatus::PROVIDER_ERROR) {
            return 0;
        }

        $fetchedAt ??= new \DateTimeImmutable();
        $stored = 0;

        $this->entityManager->wrapInTransaction(function () use ($request, $result, $fetchedAt, &$stored): void {
            $this->offerRepository->deleteExternalSnapshot($result->source, $request);

            if ($result->status === FlightOfferSearchStatus::NO_RESULTS) {
                return;
            }

            foreach ($this->deduplicate($result->candidates) as $candidate) {
                $this->entityManager->persist($this->offerFromCandidate($request, $result, $candidate, $fetchedAt));
                ++$stored;
            }
        });

        return $stored;
    }

    private function offerFromCandidate(FlightOfferSearchRequest $request, FlightOfferSearchResult $result, FlightOfferCandidate $candidate, \DateTimeImmutable $fetchedAt): FlightOffer
    {
        $offer = (new FlightOffer())
            ->setSourceType(FlightPriceSourceType::EXTERNAL)
            ->setSearchSource($result->source)
            ->setProviderCode($candidate->providerCode)
            ->setExternalOfferId($candidate->externalOfferId)
            ->setTripType($candidate->tripType)
            ->setPricingMode(FlightPricingMode::TOTAL_PARTY)
            ->setAdults($candidate->adults)
            ->setChildren($candidate->children)
            ->setInfants($candidate->infants)
            ->setCabinClass($candidate->cabinClass)
            ->setCurrency($candidate->currency)
            ->setTotalPrice($candidate->totalPrice)
            ->setBaggage($candidate->baggage)
            ->setBookingUrl($candidate->bookingUrl)
            ->setAvailabilityStatus($candidate->availabilityStatus)
            ->setPriority(50)
            ->setActive(true)
            ->setFetchedAt($fetchedAt)
            ->setExpiresAt($fetchedAt->modify('+' . $this->ttl() . ' minutes'))
            ->setMetadata($candidate->metadata + [
                'sourceIdentifier' => $candidate->sourceIdentifier,
                'sourceName' => $candidate->sourceName,
                'searchContext' => $request->context(),
                'searchContextHash' => $request->contextHash(),
                'ttlMinutes' => $this->ttl(),
            ]);

        foreach (array_merge($candidate->outboundLegs, $candidate->inboundLegs) as $legCandidate) {
            $offer->addLeg($this->legFromCandidate($legCandidate));
        }

        return $offer;
    }

    private function legFromCandidate(FlightOfferLegCandidate $candidate): FlightOfferLeg
    {
        $origin = $this->airportRepository->findOneBy(['iataCode' => $candidate->originIata]);
        $destination = $this->airportRepository->findOneBy(['iataCode' => $candidate->destinationIata]);
        if (!$origin instanceof Airport || !$destination instanceof Airport) {
            throw new \LogicException('Flight offer candidate passed validation with unknown airport IATA.');
        }

        return (new FlightOfferLeg())
            ->setDirection($candidate->direction)
            ->setSegmentIndex($candidate->segmentIndex)
            ->setAirline($this->airlineFor($candidate))
            ->setOriginAirport($origin)
            ->setDestinationAirport($destination)
            ->setFlightNumber($candidate->flightNumber)
            ->setDepartureAt($candidate->departureAt)
            ->setArrivalAt($candidate->arrivalAt)
            ->setDurationMinutes($candidate->durationMinutes)
            ->setAircraft($candidate->aircraft)
            ->setMetadata($candidate->metadata + [
                'providerAirlineName' => $candidate->airlineName,
                'providerAirlineIata' => $candidate->airlineIata,
                'providerAirlineIcao' => $candidate->airlineIcao,
            ]);
    }

    private function airlineFor(FlightOfferLegCandidate $candidate): ?Airline
    {
        if ($candidate->airlineIata === null) {
            return null;
        }

        return $this->airlineRepository->findOneByIataCode($candidate->airlineIata);
    }

    /**
     * @param FlightOfferCandidate[] $candidates
     *
     * @return FlightOfferCandidate[]
     */
    private function deduplicate(array $candidates): array
    {
        $deduplicated = [];
        foreach ($candidates as $candidate) {
            $deduplicated[$this->candidateKey($candidate)] = $candidate;
        }

        return array_values($deduplicated);
    }

    private function candidateKey(FlightOfferCandidate $candidate): string
    {
        if ($candidate->externalOfferId !== null) {
            return 'id:' . $candidate->externalOfferId;
        }

        return implode('|', [
            $candidate->providerCode,
            $candidate->currency,
            $candidate->totalPrice,
            $candidate->firstOutbound()->originIata,
            $candidate->lastOutbound()->destinationIata,
            $candidate->firstOutbound()->departureAt->format(\DateTimeInterface::ATOM),
            $candidate->bookingUrl ?? '',
        ]);
    }

    private function ttl(): int
    {
        $ttl = filter_var($this->ttlMinutes, FILTER_VALIDATE_INT);

        return \is_int($ttl) && $ttl > 0 ? $ttl : 20;
    }
}
