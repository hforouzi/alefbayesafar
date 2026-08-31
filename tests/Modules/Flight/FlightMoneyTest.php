<?php

namespace App\Tests\Modules\Flight;

use App\Modules\Flight\ValueObject\FlightMoney;
use PHPUnit\Framework\TestCase;

class FlightMoneyTest extends TestCase
{
    public function testNormalizesUnambiguousCurrencySymbolPrices(): void
    {
        self::assertSame('620.00', FlightMoney::normalize('$620'));
        self::assertSame('620.50', FlightMoney::normalize('$620.5'));
        self::assertSame('620.50', FlightMoney::normalize('620.50'));
    }

    public function testRejectsAmbiguousSeparatedPrices(): void
    {
        self::assertNull(FlightMoney::normalize('1,111'));
        self::assertNull(FlightMoney::normalize('$1,111.00'));
    }
}
