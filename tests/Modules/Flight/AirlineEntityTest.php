<?php

namespace App\Tests\Modules\Flight;

use App\Modules\Flight\Entity\Airline;
use PHPUnit\Framework\TestCase;

class AirlineEntityTest extends TestCase
{
    public function testAirlineNormalizesCodesAndActiveDefaults(): void
    {
        $airline = (new Airline())
            ->setName(' Turkish Airlines ')
            ->setNameFa(' ترکیش ایرلاینز ')
            ->setIataCode(' tk ')
            ->setIcaoCode(' thy ');

        self::assertSame('Turkish Airlines', $airline->getName());
        self::assertSame('ترکیش ایرلاینز', $airline->getNameFa());
        self::assertSame('TK', $airline->getIataCode());
        self::assertSame('THY', $airline->getIcaoCode());
        self::assertTrue($airline->isActive());
    }

    public function testAirlineCodesAreNullable(): void
    {
        $airline = (new Airline())
            ->setName('Manual Airline')
            ->setIataCode('')
            ->setIcaoCode(null)
            ->setActive(false);

        self::assertNull($airline->getIataCode());
        self::assertNull($airline->getIcaoCode());
        self::assertFalse($airline->isActive());
    }
}
