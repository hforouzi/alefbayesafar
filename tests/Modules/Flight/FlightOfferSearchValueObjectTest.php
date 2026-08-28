<?php

namespace App\Tests\Modules\Flight;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\Flight\Enum\FlightOfferSearchStatus;
use App\Modules\Flight\Enum\FlightTripType;
use App\Modules\Flight\ValueObject\FlightOfferSearchRequest;
use App\Modules\Flight\ValueObject\FlightOfferSearchResult;
use App\Modules\Flight\ValueObject\FlightOfferSearchSummary;
use App\Modules\SearchSource\Entity\SearchSource;
use PHPUnit\Framework\TestCase;

class FlightOfferSearchValueObjectTest extends TestCase
{
    public function testRequestDerivesTripTypeFromReturnDate(): void
    {
        $oneWay = new FlightOfferSearchRequest($this->airport('CGN'), $this->airport('IST'), new \DateTimeImmutable('2026-09-10'), null, 1, 0, 0, FlightCabinClass::ECONOMY);
        $roundTrip = new FlightOfferSearchRequest($this->airport('CGN'), $this->airport('IST'), new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-17'), 1, 0, 0, FlightCabinClass::ECONOMY);

        self::assertSame(FlightTripType::ONE_WAY, $oneWay->tripType);
        self::assertSame(FlightTripType::ROUND_TRIP, $roundTrip->tripType);
        self::assertSame('CGN', $roundTrip->context()['origin']);
    }

    public function testRequestRejectsInvalidPassengersAndAirports(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FlightOfferSearchRequest($this->airport('CGN'), $this->airport('CGN'), new \DateTimeImmutable('2026-09-10'), null, 0, -1, 2, FlightCabinClass::ECONOMY);
    }

    public function testProviderStatusesAreExplicit(): void
    {
        $source = (new SearchSource())->setName('Flight Source')->setDomain('example.test');
        $summary = new FlightOfferSearchSummary([
            FlightOfferSearchResult::success($source, []),
            FlightOfferSearchResult::noResults($source),
            FlightOfferSearchResult::noData($source),
            FlightOfferSearchResult::failure($source, ['timeout']),
        ]);

        self::assertTrue($summary->hasStatus(FlightOfferSearchStatus::NO_RESULTS));
        self::assertTrue($summary->hasStatus(FlightOfferSearchStatus::NO_DATA));
        self::assertTrue($summary->hasFailures());
        self::assertSame(FlightOfferSearchStatus::PROVIDER_ERROR, FlightOfferSearchResult::failure($source, ['timeout'])->status);
    }

    private function airport(string $iata): Airport
    {
        return (new Airport())->setName($iata)->setIataCode($iata);
    }
}
