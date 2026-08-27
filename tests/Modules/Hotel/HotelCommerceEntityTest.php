<?php

namespace App\Tests\Modules\Hotel;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelRate;
use App\Modules\Hotel\Entity\HotelRoomType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class HotelCommerceEntityTest extends TestCase
{
    public function testRoomTypeBelongsToHotelAndValidatesOccupancy(): void
    {
        $hotel = $this->hotel();
        $roomType = (new HotelRoomType())
            ->setHotel($hotel)
            ->setName(' Standard Double ')
            ->setNameFa(' Standard FA ')
            ->setCode(' Standard Double ')
            ->setMaxAdults(2)
            ->setMaxChildren(1)
            ->setMaxOccupancy(3)
            ->setSource(' Booking ')
            ->setExternalId('room-123')
            ->setSourceUrl('https://booking.com/room/123')
            ->setSourceName('Standard Double Source')
            ->setBedConfiguration('1 large double bed')
            ->setSizeSqm('28')
            ->setDescriptionOriginal('Source room description.')
            ->setActive(true);

        $hotel->addRoomType($roomType);

        self::assertSame($hotel, $roomType->getHotel());
        self::assertTrue($hotel->getRoomTypes()->contains($roomType));
        self::assertSame('Standard Double', $roomType->getName());
        self::assertSame('Standard FA', $roomType->getNameFa());
        self::assertSame('standard_double', $roomType->getCode());
        self::assertSame('booking', $roomType->getSource());
        self::assertSame('room-123', $roomType->getExternalId());
        self::assertSame('28.00', $roomType->getSizeSqm());
        self::assertTrue($roomType->isActive());
        self::assertCount(0, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($roomType));

        $roomType->setMaxOccupancy(2);
        self::assertGreaterThanOrEqual(1, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($roomType)->count());

        $this->expectException(\LogicException::class);
        $roomType->initializeTimestamps();
    }

    public function testImportedRoomTypeCanLeaveOccupancyUnknown(): void
    {
        $roomType = (new HotelRoomType())
            ->setHotel($this->hotel())
            ->setName('Mystery Room')
            ->setSource('booking')
            ->setExternalId('unknown-occupancy');

        self::assertNull($roomType->getMaxAdults());
        self::assertNull($roomType->getMaxChildren());
        self::assertNull($roomType->getMaxOccupancy());
        self::assertCount(0, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($roomType));
    }

    public function testHotelRateValidatesDatesMoneyTravelersChildrenAgesPriorityAndActiveState(): void
    {
        $hotel = $this->hotel();
        $roomType = (new HotelRoomType())->setHotel($hotel)->setName('Standard Double');
        $rate = (new HotelRate())
            ->setHotel($hotel)
            ->setRoomType($roomType)
            ->setValidFrom(new \DateTimeImmutable('2026-09-01'))
            ->setValidTo(new \DateTimeImmutable('2026-09-30'))
            ->setAdults(2)
            ->setChildren(1)
            ->setChildrenAges([4])
            ->setBoardType(' Breakfast ')
            ->setCurrency(' eur ')
            ->setPricePerNight('120')
            ->setPriority(100)
            ->setActive(true);

        $hotel->addRate($rate);

        self::assertSame($hotel, $rate->getHotel());
        self::assertTrue($hotel->getRates()->contains($rate));
        self::assertSame('breakfast', $rate->getBoardType());
        self::assertSame('EUR', $rate->getCurrency());
        self::assertSame('120.00', $rate->getPricePerNight());
        self::assertIsString($rate->getPricePerNight());
        self::assertSame(100, $rate->getPriority());
        self::assertTrue($rate->isActive());
        self::assertTrue($rate->matches(new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), 2, 1, [4]));
        self::assertFalse($rate->matches(new \DateTimeImmutable('2026-08-31'), new \DateTimeImmutable('2026-09-05'), 2, 1, [4]));
        self::assertFalse($rate->matches(new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), 1, 1, [4]));
        self::assertFalse($rate->matches(new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), 2, 1, [8]));
        self::assertCount(0, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($rate));

        $rate->setBoardType('all inclusive');
        self::assertSame('all_inclusive', $rate->getBoardType());
        $rate->setBoardType('Half Board');
        self::assertSame('half_board', $rate->getBoardType());
    }

    public function testInvalidHotelRateRulesAreRejected(): void
    {
        $hotel = $this->hotel();
        $otherHotel = $this->hotel('Other Hotel');
        $roomType = (new HotelRoomType())->setHotel($otherHotel)->setName('Other Room');
        $rate = (new HotelRate())
            ->setHotel($hotel)
            ->setRoomType($roomType)
            ->setValidFrom(new \DateTimeImmutable('2026-09-30'))
            ->setValidTo(new \DateTimeImmutable('2026-09-01'))
            ->setAdults(0)
            ->setChildren(1)
            ->setChildrenAges([4])
            ->setCurrency('EURO')
            ->setPricePerNight('12.345');

        self::assertGreaterThanOrEqual(4, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($rate)->count());

        $this->expectException(\LogicException::class);
        $rate->initializeTimestamps();
    }

    private function hotel(string $name = 'Commerce Hotel'): Hotel
    {
        return (new Hotel())
            ->setName($name)
            ->setSlug(Hotel::normalizeSlug($name))
            ->setCity((new City())->setCountry((new Country())->setName('Turkey'))->setName('Istanbul')->setSlug('istanbul'));
    }
}
