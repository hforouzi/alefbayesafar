<?php

namespace App\Tests\Modules\Flight;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Enum\FlightAvailabilityStatus;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\Flight\Enum\FlightDirection;
use App\Modules\Flight\Enum\FlightOfferSearchStatus;
use App\Modules\Flight\Repository\FlightOfferRepository;
use App\Modules\Flight\Service\FlightOfferStoreService;
use App\Modules\Flight\ValueObject\FlightOfferCandidate;
use App\Modules\Flight\ValueObject\FlightOfferLegCandidate;
use App\Modules\Flight\ValueObject\FlightOfferSearchRequest;
use App\Modules\Flight\ValueObject\FlightOfferSearchResult;
use App\Modules\Flight\ValueObject\FlightOfferSearchSummary;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FlightOfferStoreServiceTest extends KernelTestCase
{
    public function testOffersFoundReplacesExactSnapshotAndNoResultsClearsIt(): void
    {
        self::bootKernel();
        $source = $this->source('store-replace');
        $request = $this->request();

        self::assertSame(1, $this->store()->storeResult($request, FlightOfferSearchResult::success($source, [$this->candidate('ext-a', '700.00')])));
        self::assertCount(1, $this->repository()->findExternalSnapshotOffers($source, $request));

        self::assertSame(1, $this->store()->storeResult($request, FlightOfferSearchResult::success($source, [$this->candidate('ext-b', '690.00')])));
        $stored = $this->repository()->findExternalSnapshotOffers($source, $request);
        self::assertCount(1, $stored);
        self::assertSame('ext-b', $stored[0]->getExternalOfferId());

        self::assertSame(0, $this->store()->storeResult($request, FlightOfferSearchResult::noResults($source)));
        self::assertCount(0, $this->repository()->findExternalSnapshotOffers($source, $request));
    }

    public function testNoDataAndProviderErrorPreserveExistingSnapshot(): void
    {
        self::bootKernel();
        $source = $this->source('store-preserve');
        $request = $this->request();
        $this->store()->storeResult($request, FlightOfferSearchResult::success($source, [$this->candidate('ext-keep', '700.00')]));

        self::assertSame(0, $this->store()->storeSummary($request, new FlightOfferSearchSummary([
            new FlightOfferSearchResult($source, true, [], [], [], FlightOfferSearchStatus::NO_DATA),
            FlightOfferSearchResult::failure($source, ['timeout']),
        ])));
        self::assertCount(1, $this->repository()->findExternalSnapshotOffers($source, $request));
    }

    private function candidate(string $id, string $price): FlightOfferCandidate
    {
        return new FlightOfferCandidate(
            sourceIdentifier: 'firecrawl:example.test:',
            sourceName: 'Example Flights',
            providerCode: 'firecrawl',
            externalOfferId: $id,
            tripType: $this->request()->tripType,
            adults: 2,
            children: 1,
            infants: 0,
            cabinClass: FlightCabinClass::ECONOMY,
            currency: 'EUR',
            totalPrice: $price,
            baggage: '20 kg checked',
            bookingUrl: 'https://example.test/book/' . $id,
            availabilityStatus: FlightAvailabilityStatus::AVAILABLE,
            outboundLegs: [
                new FlightOfferLegCandidate(FlightDirection::OUTBOUND, 0, 'Turkish Airlines', 'TK', null, 'CGN', 'IST', 'TK1672', new \DateTimeImmutable('2026-09-10T10:20:00+02:00'), new \DateTimeImmutable('2026-09-10T14:25:00+03:00')),
            ],
            inboundLegs: [
                new FlightOfferLegCandidate(FlightDirection::INBOUND, 0, 'Turkish Airlines', 'TK', null, 'IST', 'CGN', 'TK1671', new \DateTimeImmutable('2026-09-17T15:25:00+03:00'), new \DateTimeImmutable('2026-09-17T18:00:00+02:00')),
            ],
        );
    }

    private function request(): FlightOfferSearchRequest
    {
        return new FlightOfferSearchRequest($this->airport('CGN'), $this->airport('IST'), new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-17'), 2, 1, 0, FlightCabinClass::ECONOMY);
    }

    private function source(string $name): SearchSource
    {
        $source = (new SearchSource())
            ->setName('Flight ' . $name . random_int(1000, 9999))
            ->setDomain($name . random_int(1000, 9999) . '.example.test')
            ->setProvider('firecrawl')
            ->setProviderType(SearchSourceProviderType::FIRECRAWL)
            ->setCapabilities([SearchSource::CAPABILITY_FLIGHT])
            ->setConfig(['flightSearchUrlTemplate' => 'https://example.test/flights?from={origin}&to={destination}']);

        $this->em()->persist($source);
        $this->em()->flush();

        return $source;
    }

    private function airport(string $iata): Airport
    {
        $existing = $this->em()->getRepository(Airport::class)->findOneBy(['iataCode' => $iata]);
        if ($existing instanceof Airport) {
            return $existing;
        }

        $country = (new Country())->setName('Flight Store Test ' . $iata . random_int(1000, 9999));
        $city = (new City())->setCountry($country)->setName('Store City ' . $iata . random_int(1000, 9999));
        $airport = (new Airport())->setCity($city)->setName('Store Airport ' . $iata)->setIataCode($iata);
        foreach ([$country, $city, $airport] as $entity) {
            $this->em()->persist($entity);
        }
        $this->em()->flush();

        return $airport;
    }

    private function repository(): FlightOfferRepository
    {
        return self::getContainer()->get(FlightOfferRepository::class);
    }

    private function store(): FlightOfferStoreService
    {
        return self::getContainer()->get(FlightOfferStoreService::class);
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
