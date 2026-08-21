<?php

namespace App\Tests\Modules\Hotel;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Entity\District;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelAmenity;
use App\Modules\Hotel\Entity\HotelImage;
use App\Modules\Hotel\Entity\HotelSourceReference;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class HotelEntityTest extends KernelTestCase
{
    public function testHotelNormalizationAndRelationships(): void
    {
        $city = $this->city('Istanbul');
        $district = (new District())
            ->setCity($city)
            ->setName(' Taksim ')
            ->setSlug(' Taksim Area ');
        $amenity = (new HotelAmenity())
            ->setName(' WiFi ')
            ->setNameFa(' وای‌فای ')
            ->setCode(' Free WiFi ');

        $hotel = (new Hotel())
            ->setName(' Test Hotel ')
            ->setNameFa(' هتل تست ')
            ->setSlug(' Test Hotel Istanbul ')
            ->setCity($city)
            ->setDistrict($district)
            ->setStars(5)
            ->setLatitude('41.0123456')
            ->setLongitude('28.9876543')
            ->setWebsite('https://example.test')
            ->setPhone(' +49 221 123 ')
            ->addAmenity($amenity);

        self::assertSame('Test Hotel', $hotel->getName());
        self::assertSame('هتل تست', $hotel->getNameFa());
        self::assertSame('test-hotel-istanbul', $hotel->getSlug());
        self::assertSame($city, $hotel->getCity());
        self::assertSame($district, $hotel->getDistrict());
        self::assertSame(5, $hotel->getStars());
        self::assertSame('41.0123456', $hotel->getLatitude());
        self::assertSame('28.9876543', $hotel->getLongitude());
        self::assertSame('https://example.test', $hotel->getWebsite());
        self::assertSame('+49 221 123', $hotel->getPhone());
        self::assertTrue($hotel->isActive());
        self::assertFalse($hotel->isVerified());
        self::assertCount(1, $hotel->getAmenities());
        self::assertSame('free-wifi', $amenity->getCode());
    }

    public function testHotelValidationRules(): void
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        self::assertInstanceOf(ValidatorInterface::class, $validator);

        $hotel = (new Hotel())
            ->setName('')
            ->setSlug('bad slug!')
            ->setStars(6)
            ->setLatitude('91')
            ->setLongitude('181');

        self::assertGreaterThanOrEqual(5, $validator->validate($hotel)->count());
    }

    public function testDistrictMustBelongToHotelCity(): void
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        self::assertInstanceOf(ValidatorInterface::class, $validator);

        $city = $this->city('Istanbul');
        $otherCity = $this->city('Berlin');
        $district = (new District())
            ->setCity($otherCity)
            ->setName('Mitte')
            ->setSlug('mitte');

        $hotel = (new Hotel())
            ->setName('Mismatch Hotel')
            ->setSlug('mismatch-hotel')
            ->setCity($city)
            ->setDistrict($district);

        self::assertGreaterThanOrEqual(1, $validator->validate($hotel)->count());

        $this->expectException(\LogicException::class);
        $hotel->initializeTimestamps();
    }

    public function testImagePrimaryInvariant(): void
    {
        $hotel = (new Hotel())
            ->setName('Image Hotel')
            ->setSlug('image-hotel')
            ->setCity($this->city('Paris'));

        $first = (new HotelImage())
            ->setPath('https://example.test/one.jpg')
            ->setPosition(0)
            ->setPrimary(true);
        $second = (new HotelImage())
            ->setPath('/uploads/two.jpg')
            ->setPosition(1)
            ->setPrimary(true);

        $hotel->addImage($first);
        $hotel->addImage($second);

        self::assertFalse($first->isPrimary());
        self::assertTrue($second->isPrimary());
        self::assertSame($hotel, $first->getHotel());
        self::assertSame($hotel, $second->getHotel());

        $first->setPrimary(true);

        self::assertTrue($first->isPrimary());
        self::assertFalse($second->isPrimary());
    }

    public function testImagePathIsRequiredBeforePersistence(): void
    {
        $image = (new HotelImage())
            ->setHotel((new Hotel())->setName('Path Hotel')->setSlug('path-hotel')->setCity($this->city('Rome')));

        $this->expectException(\LogicException::class);
        $image->initializeTimestamps();
    }

    public function testSourceReferenceNormalization(): void
    {
        $hotel = (new Hotel())
            ->setName('Source Hotel')
            ->setSlug('source-hotel')
            ->setCity($this->city('Paris'));

        $sourceReference = (new HotelSourceReference())
            ->setHotel($hotel)
            ->setSource(' Booking ')
            ->setExternalId(' 123 ')
            ->setSourceUrl('https://example.test/hotel')
            ->setSourceTitle(' Source Hotel ')
            ->setMetadata(['source' => 'manual']);

        self::assertSame('booking', $sourceReference->getSource());
        self::assertSame('123', $sourceReference->getExternalId());
        self::assertSame('Source Hotel', $sourceReference->getSourceTitle());
        self::assertSame(['source' => 'manual'], $sourceReference->getMetadata());
    }

    private function city(string $name): City
    {
        $country = (new Country())
            ->setName('Country ' . $name);

        return (new City())
            ->setCountry($country)
            ->setName($name)
            ->setSlug($name);
    }
}
