<?php

namespace App\Tests\Modules\Destination;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Entity\District;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DestinationEntityTest extends KernelTestCase
{
    public function testRelationshipsAndNormalization(): void
    {
        $country = (new Country())
            ->setName(' Turkey ')
            ->setNameFa(' ترکیه ')
            ->setIso2(' tr ')
            ->setIso3(' tur ');

        $city = (new City())
            ->setCountry($country)
            ->setName(' Istanbul ')
            ->setSlug(' Istanbul City ');

        $district = (new District())
            ->setCity($city)
            ->setName(' Taksim ')
            ->setSlug(' Taksim Square ');

        $airport = (new Airport())
            ->setCity($city)
            ->setName(' Istanbul Airport ')
            ->setIataCode(' ist ')
            ->setIcaoCode(' list ')
            ->setLatitude('41.2752780')
            ->setLongitude('28.7519440');

        self::assertSame('Turkey', $country->getName());
        self::assertSame('ترکیه', $country->getNameFa());
        self::assertSame('TR', $country->getIso2());
        self::assertSame('TUR', $country->getIso3());
        self::assertSame($country, $city->getCountry());
        self::assertSame('istanbul-city', $city->getSlug());
        self::assertSame($city, $district->getCity());
        self::assertSame('taksim-square', $district->getSlug());
        self::assertSame($city, $airport->getCity());
        self::assertSame('IST', $airport->getIataCode());
        self::assertSame('LIST', $airport->getIcaoCode());
    }

    public function testValidationRules(): void
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);

        $country = (new Country())
            ->setName('')
            ->setIso2('T')
            ->setIso3('TURK');

        $airport = (new Airport())
            ->setName('Invalid Airport')
            ->setIataCode('TOOLONG')
            ->setIcaoCode('TOO')
            ->setLatitude('91')
            ->setLongitude('181');

        self::assertGreaterThanOrEqual(3, $validator->validate($country)->count());
        self::assertGreaterThanOrEqual(5, $validator->validate($airport)->count());
    }
}
