<?php

namespace App\Tests\Modules\Tour;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Enum\FlightPriceSourceType;
use App\Modules\Flight\Enum\FlightPricingMode;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelRoomType;
use App\Modules\Tour\Entity\TourPackage;
use App\Modules\Tour\Entity\TourPackageImage;
use App\Modules\Tour\Enum\TourPricingMode;
use App\Modules\Tour\ValueObject\TourMoney;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TourPackageEntityTest extends KernelTestCase
{
    public function testValidFixedDeparturePackageStoresDecimalSafeTotalPrice(): void
    {
        $package = $this->validPackage()
            ->setPricingMode(TourPricingMode::TOTAL_PARTY)
            ->setTotalPrice('1490')
            ->setAdultPrice(null)
            ->setChildPrice(null);

        self::assertSame('1490.00', $package->getTotalPrice());
        self::assertSame('1490.00', $package->calculatedPartyPrice());
        self::assertSame(0, \count($this->violations($package)));
    }

    public function testValidityRangePackageIsValidWithoutFixedDeparture(): void
    {
        $package = $this->validPackage()
            ->setDepartureDate(null)
            ->setReturnDate(null)
            ->setValidFrom(new \DateTimeImmutable('2026-09-01'))
            ->setValidTo(new \DateTimeImmutable('2026-09-30'));

        self::assertSame(0, \count($this->violations($package)));
    }

    public function testInvalidDateRangesAreRejected(): void
    {
        $package = $this->validPackage()
            ->setDepartureDate(new \DateTimeImmutable('2026-09-15'))
            ->setReturnDate(new \DateTimeImmutable('2026-09-10'))
            ->setValidFrom(new \DateTimeImmutable('2026-10-01'))
            ->setValidTo(new \DateTimeImmutable('2026-09-01'));

        self::assertGreaterThanOrEqual(2, \count($this->violations($package)));
    }

    public function testPassengerAndChildAgeValidation(): void
    {
        $package = $this->validPackage()
            ->setAdults(0)
            ->setChildren(2)
            ->setChildrenAges([8]);

        self::assertGreaterThanOrEqual(2, \count($this->violations($package)));

        $package
            ->setAdults(2)
            ->setChildren(0)
            ->setChildrenAges([8]);

        self::assertSame([], $package->getChildrenAges());
    }

    public function testPerPassengerPricingCalculatesPartyPriceWithoutFloats(): void
    {
        $package = $this->validPackage()
            ->setPricingMode(TourPricingMode::PER_PASSENGER_TYPE)
            ->setAdults(2)
            ->setChildren(1)
            ->setInfants(0)
            ->setTotalPrice(null)
            ->setAdultPrice('600')
            ->setChildPrice('400')
            ->setInfantPrice(null);

        self::assertSame('600.00', $package->getAdultPrice());
        self::assertSame('400.00', $package->getChildPrice());
        self::assertSame('1600.00', $package->calculatedPartyPrice());
        self::assertSame(0, \count($this->violations($package)));
        self::assertSame(160000, TourMoney::cents('1600.00'));
    }

    public function testExternalFlightOfferCannotBeLinkedToTourPackage(): void
    {
        $package = $this->validPackage()
            ->setFlightOffer((new FlightOffer())->setSourceType(FlightPriceSourceType::EXTERNAL));

        self::assertGreaterThan(0, \count($this->violations($package)));
    }

    public function testOwnFlightOfferAndMatchingHotelRoomTypeAreAccepted(): void
    {
        $hotel = $this->hotel('arts');
        $roomType = (new HotelRoomType())->setHotel($hotel)->setName('Double Room');
        $package = $this->validPackage()
            ->setFlightOffer((new FlightOffer())->setSourceType(FlightPriceSourceType::OWN)->setPricingMode(FlightPricingMode::TOTAL_PARTY)->setTotalPrice('780'))
            ->setHotel($hotel)
            ->setHotelRoomType($roomType);

        self::assertSame(0, \count($this->violations($package)));
    }

    public function testRoomTypeFromAnotherHotelIsRejected(): void
    {
        $package = $this->validPackage()
            ->setHotel($this->hotel('one'))
            ->setHotelRoomType((new HotelRoomType())->setHotel($this->hotel('two'))->setName('Suite'));

        self::assertGreaterThan(0, \count($this->violations($package)));
    }

    public function testImagesSupportSinglePrimary(): void
    {
        $package = $this->validPackage();
        $first = (new TourPackageImage())->setPath('/uploads/tours/1/one.jpg')->setPrimary(true);
        $second = (new TourPackageImage())->setPath('/uploads/tours/1/two.jpg')->setPrimary(true);

        $package->addImage($first);
        $package->addImage($second);

        self::assertFalse($first->isPrimary());
        self::assertTrue($second->isPrimary());
        self::assertSame($second, $package->getPrimaryImage());
    }

    /**
     * @return \Symfony\Component\Validator\ConstraintViolationListInterface
     */
    private function violations(TourPackage $package)
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        self::assertInstanceOf(ValidatorInterface::class, $validator);

        return $validator->validate($package);
    }

    private function validPackage(): TourPackage
    {
        return (new TourPackage())
            ->setName('Istanbul 5 Nights')
            ->setSlug('istanbul-5-nights-' . strtolower(bin2hex(random_bytes(3))))
            ->setOriginAirport($this->airport('CGN'))
            ->setDestinationCity($this->city('Istanbul'))
            ->setDepartureDate(new \DateTimeImmutable('2026-09-10'))
            ->setReturnDate(new \DateTimeImmutable('2026-09-15'))
            ->setNights(5)
            ->setDays(6)
            ->setAdults(2)
            ->setChildren(1)
            ->setInfants(0)
            ->setChildrenAges([8])
            ->setPricingMode(TourPricingMode::TOTAL_PARTY)
            ->setCurrency('eur')
            ->setTotalPrice('1490')
            ->setInclusions(['flight', 'hotel', 'breakfast', 'airport_transfer'])
            ->setExclusions(['visa', 'travel_insurance'])
            ->setPriority(100)
            ->setActive(true)
            ->setFeatured(true)
            ->setPublicVisible(true);
    }

    private function hotel(string $name): Hotel
    {
        return (new Hotel())->setName('Hotel ' . $name)->setSlug('hotel-' . $name)->setCity($this->city('Istanbul'));
    }

    private function airport(string $iata): Airport
    {
        return (new Airport())->setName($iata . ' Airport')->setIataCode($iata)->setCity($this->city('Cologne'));
    }

    private function city(string $name): City
    {
        return (new City())->setName($name)->setSlug(strtolower($name))->setCountry((new Country())->setName('Test Country'));
    }
}
