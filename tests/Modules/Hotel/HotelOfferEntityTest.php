<?php

namespace App\Tests\Modules\Hotel;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelOffer;
use App\Modules\SearchSource\Entity\SearchSource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class HotelOfferEntityTest extends TestCase
{
    public function testOfferStoresCommercialSourceProviderStayTravelersAndMoney(): void
    {
        $hotel = $this->hotel();
        $source = $this->searchSource();
        $fetchedAt = new \DateTimeImmutable('2026-08-24 10:00:00');

        $offer = (new HotelOffer())
            ->setHotel($hotel)
            ->setSearchSource($source)
            ->setProviderCode(' Fire Crawl ')
            ->setExternalOfferId(' offer-123 ')
            ->setCheckIn(new \DateTimeImmutable('2026-09-10'))
            ->setCheckOut(new \DateTimeImmutable('2026-09-15'))
            ->setAdults(2)
            ->setChildren(1)
            ->setChildrenAges([4])
            ->setRoomName(' Deluxe Double Room ')
            ->setBoardType(' Breakfast included ')
            ->setCurrency(' eur ')
            ->setTotalPrice('620.00')
            ->setBookingUrl('https://booking.example.test/hotel/offer-123')
            ->setAvailabilityStatus(' AVAILABLE ')
            ->setFetchedAt($fetchedAt)
            ->setMetadata(['ratePlan' => 'breakfast', 'raw' => ['providerRank' => 1]]);

        $hotel->addOffer($offer);

        self::assertSame($hotel, $offer->getHotel());
        self::assertSame($source, $offer->getSearchSource());
        self::assertSame('fire_crawl', $offer->getProviderCode());
        self::assertSame('offer-123', $offer->getExternalOfferId());
        self::assertSame('2026-09-10', $offer->getCheckIn()?->format('Y-m-d'));
        self::assertSame('2026-09-15', $offer->getCheckOut()?->format('Y-m-d'));
        self::assertSame(2, $offer->getAdults());
        self::assertSame(1, $offer->getChildren());
        self::assertSame([4], $offer->getChildrenAges());
        self::assertSame('Deluxe Double Room', $offer->getRoomName());
        self::assertSame('Breakfast included', $offer->getBoardType());
        self::assertSame('EUR', $offer->getCurrency());
        self::assertSame('620.00', $offer->getTotalPrice());
        self::assertIsString($offer->getTotalPrice());
        self::assertSame('https://booking.example.test/hotel/offer-123', $offer->getBookingUrl());
        self::assertSame(HotelOffer::AVAILABILITY_AVAILABLE, $offer->getAvailabilityStatus());
        self::assertSame($fetchedAt, $offer->getFetchedAt());
        self::assertSame(['ratePlan' => 'breakfast', 'raw' => ['providerRank' => 1]], $offer->getMetadata());
        self::assertTrue($hotel->getOffers()->contains($offer));
    }

    public function testOptionalOfferFieldsCanBeCleared(): void
    {
        $offer = (new HotelOffer())
            ->setExternalOfferId(' ')
            ->setRoomName(' ')
            ->setBoardType(' ')
            ->setBookingUrl(' ')
            ->setAvailabilityStatus(null);

        self::assertNull($offer->getExternalOfferId());
        self::assertNull($offer->getRoomName());
        self::assertNull($offer->getBoardType());
        self::assertNull($offer->getBookingUrl());
        self::assertNull($offer->getAvailabilityStatus());
    }

    public function testValidOfferPassesEntityValidation(): void
    {
        self::assertCount(0, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($this->validOffer()));
    }

    public function testInvalidStayDatesAreRejected(): void
    {
        $offer = $this->validOffer()
            ->setCheckIn(new \DateTimeImmutable('2026-09-10'))
            ->setCheckOut(new \DateTimeImmutable('2026-09-10'));

        self::assertGreaterThanOrEqual(1, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($offer)->count());

        $this->expectException(\LogicException::class);
        $offer->initializeTimestamps();
    }

    public function testTravelerCountsAreValidated(): void
    {
        $offer = $this->validOffer()
            ->setAdults(0)
            ->setChildren(-1);

        self::assertGreaterThanOrEqual(2, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($offer)->count());
    }

    public function testChildrenAgesMustMatchChildrenCount(): void
    {
        $offer = $this->validOffer()
            ->setChildren(1)
            ->setChildrenAges([]);

        self::assertGreaterThanOrEqual(1, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($offer)->count());

        $this->expectException(\LogicException::class);
        $offer->initializeTimestamps();
    }

    public function testCurrencyAndPriceAreValidatedWithoutFloats(): void
    {
        $offer = $this->validOffer()
            ->setCurrency('EURO')
            ->setTotalPrice('12.345');

        self::assertGreaterThanOrEqual(2, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($offer)->count());
    }

    public function testTimestampsInitializeOnPersistLifecycle(): void
    {
        $offer = $this->validOffer()
            ->setFetchedAt(null);

        $offer->initializeTimestamps();

        self::assertInstanceOf(\DateTimeImmutable::class, $offer->getFetchedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $offer->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $offer->getUpdatedAt());
    }

    private function validOffer(): HotelOffer
    {
        return (new HotelOffer())
            ->setHotel($this->hotel())
            ->setSearchSource($this->searchSource())
            ->setProviderCode('firecrawl')
            ->setCheckIn(new \DateTimeImmutable('2026-09-10'))
            ->setCheckOut(new \DateTimeImmutable('2026-09-15'))
            ->setAdults(2)
            ->setChildren(0)
            ->setChildrenAges([])
            ->setCurrency('EUR')
            ->setTotalPrice('620.00')
            ->setFetchedAt(new \DateTimeImmutable('2026-08-24 10:00:00'))
            ->setMetadata([]);
    }

    private function hotel(): Hotel
    {
        return (new Hotel())
            ->setName('Arts Hotel Istanbul')
            ->setSlug('arts-hotel-istanbul')
            ->setCity((new City())->setCountry((new Country())->setName('Turkey'))->setName('Istanbul')->setSlug('istanbul'));
    }

    private function searchSource(): SearchSource
    {
        return (new SearchSource())
            ->setName('Booking')
            ->setDomain('booking.example.test')
            ->setProvider('firecrawl')
            ->setCapabilities([SearchSource::CAPABILITY_HOTEL]);
    }
}
