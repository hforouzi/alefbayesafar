<?php

namespace App\Tests\Modules\Flight;

use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\Flight\Enum\FlightPriceSourceType;
use App\Modules\Flight\Enum\FlightPricingMode;
use App\Modules\Flight\Enum\FlightTripType;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class FlightOfferEntityTest extends TestCase
{
    public function testOwnOfferClearsExternalSnapshotFieldsAndStoresPricing(): void
    {
        $offer = $this->validOwnOffer()
            ->setCabinClass(FlightCabinClass::BUSINESS)
            ->setAdultPrice('280')
            ->setChildPrice('220.5')
            ->setPriority(100);

        self::assertSame(FlightPriceSourceType::OWN, $offer->getSourceType());
        self::assertNull($offer->getSearchSource());
        self::assertNull($offer->getFetchedAt());
        self::assertNull($offer->getExpiresAt());
        self::assertSame(FlightCabinClass::BUSINESS, $offer->getCabinClass());
        self::assertSame('280.00', $offer->getAdultPrice());
        self::assertSame('220.50', $offer->getChildPrice());
        self::assertSame(100, $offer->getPriority());
    }

    public function testPerPassengerPricingRequiresPassengerPricesForIncludedPassengers(): void
    {
        $offer = $this->validOwnOffer()
            ->setAdults(2)
            ->setChildren(1)
            ->setInfants(1)
            ->setAdultPrice('280.00')
            ->setChildPrice(null)
            ->setInfantPrice(null);

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($offer);

        self::assertGreaterThanOrEqual(2, \count($violations));
    }

    public function testTotalPartyPricingRequiresTotalPrice(): void
    {
        $offer = $this->validOwnOffer()
            ->setPricingMode(FlightPricingMode::TOTAL_PARTY)
            ->setAdultPrice(null)
            ->setTotalPrice(null);

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($offer);

        self::assertGreaterThan(0, \count($violations));
    }

    public function testExternalOfferRequiresSearchSourceAndFetchedAt(): void
    {
        $offer = $this->validOwnOffer()
            ->setSourceType(FlightPriceSourceType::EXTERNAL);

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($offer);

        self::assertGreaterThanOrEqual(2, \count($violations));
    }

    public function testExternalOfferCanRepresentFutureProviderSnapshot(): void
    {
        $source = (new SearchSource())
            ->setName('Flight Source')
            ->setDomain('flights.example.test')
            ->setProvider('firecrawl')
            ->setProviderType(SearchSourceProviderType::FIRECRAWL)
            ->setCapabilities([SearchSource::CAPABILITY_FLIGHT]);

        $offer = $this->validOwnOffer()
            ->setSourceType(FlightPriceSourceType::EXTERNAL)
            ->setSearchSource($source)
            ->setPricingMode(FlightPricingMode::TOTAL_PARTY)
            ->setAdultPrice(null)
            ->setChildPrice(null)
            ->setInfantPrice(null)
            ->setTotalPrice('780')
            ->setFetchedAt(new \DateTimeImmutable('2026-08-28 10:00:00'))
            ->setExpiresAt(new \DateTimeImmutable('2026-08-28 10:30:00'));

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($offer);

        self::assertCount(0, $violations);
    }

    public function testTripTypeAndPricingModeUseBackedEnums(): void
    {
        $offer = $this->validOwnOffer()
            ->setTripType(FlightTripType::ROUND_TRIP)
            ->setPricingMode(FlightPricingMode::PER_PASSENGER_TYPE);

        self::assertSame(FlightTripType::ROUND_TRIP, $offer->getTripType());
        self::assertSame(FlightPricingMode::PER_PASSENGER_TYPE, $offer->getPricingMode());
        self::assertSame('Adult 280.00 EUR / Child 220.00 EUR', $offer->getPriceLabel());
    }

    private function validOwnOffer(): FlightOffer
    {
        return (new FlightOffer())
            ->setSourceType(FlightPriceSourceType::OWN)
            ->setTripType(FlightTripType::ONE_WAY)
            ->setPricingMode(FlightPricingMode::PER_PASSENGER_TYPE)
            ->setAdults(2)
            ->setChildren(1)
            ->setInfants(0)
            ->setCabinClass(FlightCabinClass::ECONOMY)
            ->setCurrency('eur')
            ->setAdultPrice('280.00')
            ->setChildPrice('220.00');
    }
}
