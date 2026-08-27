<?php

namespace App\Tests\Modules\Hotel;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Service\HotelSourceIdentityMatcher;
use PHPUnit\Framework\TestCase;

class HotelSourceIdentityMatcherTest extends TestCase
{
    public function testCanonicalHotelMatchesBookingMarketingTitle(): void
    {
        $result = $this->matcher()->evaluate(
            $this->hotel('Arts Hotel Taksim'),
            'Arts Hotel Taksim, Istanbul (updated prices 2026)',
            'https://www.booking.com/hotel/tr/arts-hotel-taksim.html',
        );

        self::assertTrue($result['match']);
    }

    public function testCanonicalHotelRejectsUnrelatedBookingReference(): void
    {
        $result = $this->matcher()->evaluate(
            $this->hotel('Arts Hotel Taksim'),
            'Grand Oztanik Hotel',
            'https://www.booking.com/hotel/tr/grand-oztanik.html',
        );

        self::assertFalse($result['match']);
        self::assertSame('source URL slug contradicted canonical hotel identity', $result['reason']);
    }

    public function testCanonicalHotelRejectsDifferentHotelWithSharedBrandToken(): void
    {
        $result = $this->matcher()->evaluate(
            $this->hotel('Arts Hotel Taksim'),
            'Arts Hotel Istanbul Harbiye - Special Class',
            'https://www.booking.com/hotel/tr/arts-hotel-harbiye.html',
        );

        self::assertFalse($result['match']);
    }

    private function matcher(): HotelSourceIdentityMatcher
    {
        return new HotelSourceIdentityMatcher();
    }

    private function hotel(string $name): Hotel
    {
        return (new Hotel())
            ->setName($name)
            ->setSlug('arts-hotel-taksim')
            ->setCity((new City())->setCountry((new Country())->setName('Turkey'))->setName('Istanbul')->setSlug('istanbul'));
    }
}
