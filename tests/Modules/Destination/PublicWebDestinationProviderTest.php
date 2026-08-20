<?php

namespace App\Tests\Modules\Destination;

use App\Modules\Destination\Provider\BookingDestinationProvider;
use App\Modules\Destination\ValueObject\DestinationEntityType;
use App\Modules\Destination\ValueObject\DestinationImportRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class PublicWebDestinationProviderTest extends TestCase
{
    public function testBookingChallengeResponseIsReportedAsProviderFailure(): void
    {
        $html = '<html><script>window.awsWafCookieDomainList = ["booking.com"];</script><noscript>not a robot</noscript></html>';
        $provider = new BookingDestinationProvider(new MockHttpClient([
            new MockResponse($html, ['http_code' => 202]),
        ]));

        $result = $provider->discover(new DestinationImportRequest(
            targetType: DestinationEntityType::CITY,
            countryName: 'Turkey',
            cityName: 'Istanbul',
            providerCodes: ['booking'],
            refresh: true,
        ));

        self::assertFalse($result->success);
        self::assertSame(['Provider returned a JavaScript/WAF challenge instead of destination content.'], $result->errors);
        self::assertSame([], $result->candidates);
    }
}
