<?php

namespace App\Tests\Modules\Flight;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Entity\FlightOfferLeg;
use App\Modules\Flight\Enum\FlightAvailabilityStatus;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\Flight\Enum\FlightDirection;
use App\Modules\Flight\Enum\FlightPriceSourceType;
use App\Modules\Flight\Enum\FlightPricingMode;
use App\Modules\Flight\Enum\FlightTripType;
use App\Modules\Flight\Service\FlightPricingResolver;
use App\Modules\Flight\ValueObject\FlightOfferSearchRequest;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FlightPricingResolverTest extends KernelTestCase
{
    public function testOwnPerPassengerCalculationAndOwnPriorityBeforeCheaperExternal(): void
    {
        self::bootKernel();
        $cgn = $this->airport('CGN');
        $ist = $this->airport('IST');
        $departureDate = $this->uniqueDate();
        $request = new FlightOfferSearchRequest($cgn, $ist, $departureDate, null, 2, 1, 0, FlightCabinClass::ECONOMY);
        $own = $this->ownOffer($cgn, $ist, $departureDate, FlightPricingMode::PER_PASSENGER_TYPE, null, '280.00', '220.00');
        $external = $this->externalOffer($request, '690.00', '+20 minutes');

        $candidates = $this->resolver()->resolve($cgn, $ist, $departureDate, null, 2, 1, 0, FlightCabinClass::ECONOMY);

        self::assertSame($own->getId(), $candidates[0]->flightOfferId);
        self::assertSame('780.00', $candidates[0]->totalPrice);
        self::assertSame($external->getId(), $candidates[1]->flightOfferId);
        self::assertSame('690.00', $candidates[1]->totalPrice);
    }

    public function testOwnTotalPartyRequiresMatchingPartyAndExpiredExternalIgnored(): void
    {
        self::bootKernel();
        $cgn = $this->airport('CGN');
        $ist = $this->airport('IST');
        $departureDate = $this->uniqueDate();
        $this->ownOffer($cgn, $ist, $departureDate, FlightPricingMode::TOTAL_PARTY, '780.00', null, null, 2, 1);
        $request = new FlightOfferSearchRequest($cgn, $ist, $departureDate, null, 2, 1, 0, FlightCabinClass::ECONOMY);
        $this->externalOffer($request, '690.00', '-1 minute');

        $matching = $this->resolver()->resolve($cgn, $ist, $departureDate, null, 2, 1, 0, FlightCabinClass::ECONOMY);
        $wrongParty = $this->resolver()->resolve($cgn, $ist, $departureDate, null, 1, 1, 0, FlightCabinClass::ECONOMY);

        self::assertCount(1, $matching);
        self::assertSame('780.00', $matching[0]->totalPrice);
        self::assertCount(0, $wrongParty);
    }

    public function testWrongRouteDateCabinAndDirectOnlyAreIgnored(): void
    {
        self::bootKernel();
        $cgn = $this->airport('CGN');
        $fra = $this->airport('FRA');
        $ist = $this->airport('IST');
        $departureDate = $this->uniqueDate();
        $this->ownOffer($cgn, $ist, $departureDate, FlightPricingMode::PER_PASSENGER_TYPE, null, '280.00', null, 1, 0, FlightCabinClass::BUSINESS);
        $this->ownOffer($cgn, $fra, $departureDate, FlightPricingMode::PER_PASSENGER_TYPE, null, '100.00', null, 1, 0);

        self::assertCount(0, $this->resolver()->resolve($cgn, $ist, $departureDate->modify('+1 day'), null, 1, 0, 0, FlightCabinClass::ECONOMY));
        self::assertCount(0, $this->resolver()->resolve($cgn, $ist, $departureDate, null, 1, 0, 0, FlightCabinClass::ECONOMY));
        self::assertCount(0, $this->resolver()->resolve($cgn, $fra, $departureDate, null, 1, 0, 0, FlightCabinClass::BUSINESS));
    }

    private function ownOffer(Airport $origin, Airport $destination, \DateTimeImmutable $departureDate, FlightPricingMode $mode, ?string $totalPrice, ?string $adultPrice, ?string $childPrice, int $adults = 2, int $children = 1, FlightCabinClass $cabinClass = FlightCabinClass::ECONOMY): FlightOffer
    {
        $offer = (new FlightOffer())
            ->setSourceType(FlightPriceSourceType::OWN)
            ->setTripType(FlightTripType::ONE_WAY)
            ->setPricingMode($mode)
            ->setAdults($adults)
            ->setChildren($children)
            ->setInfants(0)
            ->setCabinClass($cabinClass)
            ->setCurrency('EUR')
            ->setTotalPrice($totalPrice)
            ->setAdultPrice($adultPrice)
            ->setChildPrice($childPrice)
            ->setPriority(100)
            ->addLeg((new FlightOfferLeg())->setDirection(FlightDirection::OUTBOUND)->setSegmentIndex(0)->setOriginAirport($origin)->setDestinationAirport($destination)->setDepartureAt($departureDate->setTime(10, 20))->setArrivalAt($departureDate->setTime(14, 25)));

        $this->em()->persist($offer);
        $this->em()->flush();

        return $offer;
    }

    private function externalOffer(FlightOfferSearchRequest $request, string $price, string $expiresModifier): FlightOffer
    {
        $source = (new SearchSource())->setName('Resolver External ' . random_int(1000, 9999))->setDomain('resolver' . random_int(1000, 9999) . '.example.test')->setProvider('firecrawl')->setProviderType(SearchSourceProviderType::FIRECRAWL)->setCapabilities([SearchSource::CAPABILITY_FLIGHT])->setConfig(['flightSearchUrlTemplate' => 'https://example.test']);
        $this->em()->persist($source);

        $offer = (new FlightOffer())
            ->setSourceType(FlightPriceSourceType::EXTERNAL)
            ->setSearchSource($source)
            ->setProviderCode('firecrawl')
            ->setTripType($request->tripType)
            ->setPricingMode(FlightPricingMode::TOTAL_PARTY)
            ->setAdults($request->adults)
            ->setChildren($request->children)
            ->setInfants($request->infants)
            ->setCabinClass($request->cabinClass)
            ->setCurrency('EUR')
            ->setTotalPrice($price)
            ->setAvailabilityStatus(FlightAvailabilityStatus::AVAILABLE)
            ->setPriority(50)
            ->setFetchedAt(new \DateTimeImmutable('-1 minute'))
            ->setExpiresAt((new \DateTimeImmutable())->modify($expiresModifier))
            ->setMetadata(['searchContextHash' => $request->contextHash(), 'searchContext' => $request->context()])
            ->addLeg((new FlightOfferLeg())->setDirection(FlightDirection::OUTBOUND)->setSegmentIndex(0)->setOriginAirport($request->originAirport)->setDestinationAirport($request->destinationAirport)->setDepartureAt($request->departureDate->setTime(10, 20))->setArrivalAt($request->departureDate->setTime(14, 25)));

        $this->em()->persist($offer);
        $this->em()->flush();

        return $offer;
    }

    private function airport(string $iata): Airport
    {
        $existing = $this->em()->getRepository(Airport::class)->findOneBy(['iataCode' => $iata]);
        if ($existing instanceof Airport) {
            return $existing;
        }

        $country = (new Country())->setName('Flight Resolver Test ' . $iata . random_int(1000, 9999));
        $city = (new City())->setCountry($country)->setName('Resolver City ' . $iata . random_int(1000, 9999));
        $airport = (new Airport())->setCity($city)->setName('Resolver Airport ' . $iata)->setIataCode($iata);
        foreach ([$country, $city, $airport] as $entity) {
            $this->em()->persist($entity);
        }
        $this->em()->flush();

        return $airport;
    }

    private function resolver(): FlightPricingResolver
    {
        return new FlightPricingResolver(self::getContainer()->get(\App\Modules\Flight\Repository\FlightOfferRepository::class));
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function uniqueDate(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('2035-01-01'))->modify('+' . random_int(0, 3650) . ' days');
    }
}
