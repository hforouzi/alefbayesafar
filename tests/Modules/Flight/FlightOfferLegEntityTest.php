<?php

namespace App\Tests\Modules\Flight;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Flight\Entity\Airline;
use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Entity\FlightOfferLeg;
use App\Modules\Flight\Enum\FlightDirection;
use App\Modules\Flight\Enum\FlightTripType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class FlightOfferLegEntityTest extends TestCase
{
    public function testLegReferencesCanonicalAirlineAndAirports(): void
    {
        $airline = (new Airline())->setName('Turkish Airlines')->setIataCode('TK');
        $origin = $this->airport('CGN');
        $destination = $this->airport('IST');

        $leg = $this->leg($origin, $destination)
            ->setAirline($airline)
            ->setFlightNumber(' tk1672 ')
            ->setSegmentIndex(0);

        self::assertSame($airline, $leg->getAirline());
        self::assertSame($origin, $leg->getOriginAirport());
        self::assertSame($destination, $leg->getDestinationAirport());
        self::assertSame('TK1672', $leg->getFlightNumber());
        self::assertSame(0, $leg->getSegmentIndex());
    }

    public function testArrivalMustBeAfterDeparture(): void
    {
        $leg = $this->leg($this->airport('CGN'), $this->airport('IST'))
            ->setDepartureAt(new \DateTimeImmutable('2026-09-10 10:20:00'))
            ->setArrivalAt(new \DateTimeImmutable('2026-09-10 10:20:00'));

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($leg);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testOriginAndDestinationMustDiffer(): void
    {
        $airport = $this->airport('CGN');
        $leg = $this->leg($airport, $airport);

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($leg);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testOneWayOfferRejectsInboundLegs(): void
    {
        $offer = (new FlightOffer())->setTripType(FlightTripType::ONE_WAY)->setAdultPrice('100.00');
        $offer->addLeg($this->leg($this->airport('IST'), $this->airport('CGN'))->setDirection(FlightDirection::INBOUND));

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($offer);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testRoundTripSupportsOutboundAndInboundWithConnections(): void
    {
        $offer = (new FlightOffer())
            ->setTripType(FlightTripType::ROUND_TRIP)
            ->setAdults(2)
            ->setChildren(1)
            ->setAdultPrice('280.00')
            ->setChildPrice('220.00');

        $cgn = $this->airport('CGN');
        $fra = $this->airport('FRA');
        $ist = $this->airport('IST');

        $offer
            ->addLeg($this->leg($cgn, $fra)->setDirection(FlightDirection::OUTBOUND)->setSegmentIndex(0))
            ->addLeg($this->leg($fra, $ist)->setDirection(FlightDirection::OUTBOUND)->setSegmentIndex(1))
            ->addLeg($this->leg($ist, $cgn)->setDirection(FlightDirection::INBOUND)->setSegmentIndex(0));

        self::assertCount(2, $offer->getOrderedLegs(FlightDirection::OUTBOUND));
        self::assertCount(1, $offer->getOrderedLegs(FlightDirection::INBOUND));
        self::assertSame('CGN -> IST', $offer->getRouteLabel());
    }

    private function leg(Airport $origin, Airport $destination): FlightOfferLeg
    {
        return (new FlightOfferLeg())
            ->setOriginAirport($origin)
            ->setDestinationAirport($destination)
            ->setDepartureAt(new \DateTimeImmutable('2026-09-10 10:20:00'))
            ->setArrivalAt(new \DateTimeImmutable('2026-09-10 14:25:00'));
    }

    private function airport(string $iata): Airport
    {
        $country = (new Country())->setName('Test Country');
        $city = (new City())->setCountry($country)->setName('Test City');

        return (new Airport())
            ->setCity($city)
            ->setName($iata . ' Airport')
            ->setIataCode($iata);
    }
}
