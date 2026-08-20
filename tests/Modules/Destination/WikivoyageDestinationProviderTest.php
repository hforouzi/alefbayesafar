<?php

namespace App\Tests\Modules\Destination;

use App\Modules\Destination\Provider\WikivoyageDestinationProvider;
use App\Modules\Destination\ValueObject\DestinationEntityType;
use App\Modules\Destination\ValueObject\DestinationImportRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class WikivoyageDestinationProviderTest extends TestCase
{
    public function testProviderExtractsRegionListItemsAsDistrictCandidates(): void
    {
        $html = <<<'HTML'
<div class="mw-parser-output">
<table class="regionlistitem-table"><tbody><tr>
<td class="regionlistitem-textholder"><b><a href="/wiki/Istanbul/Galata" title="Istanbul/Galata">Galata</a></b> <br />With Beyoğlu and Taksim Square.</td>
</tr></tbody></table>
<table class="regionlistitem-table"><tbody><tr>
<td class="regionlistitem-textholder"><b><a href="/wiki/Istanbul/Kadikoy" title="Istanbul/Kadikoy">Kadıköy</a></b> <br />The locals' favorite Asian-side district.</td>
</tr></tbody></table>
</div>
HTML;
        $client = new MockHttpClient([
            new MockResponse(json_encode(['parse' => ['text' => $html]], JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]);
        $provider = new WikivoyageDestinationProvider($client);

        $result = $provider->discover(new DestinationImportRequest(
            targetType: DestinationEntityType::CITY,
            countryName: 'Turkey',
            cityName: 'Istanbul',
            providerCodes: ['wikivoyage'],
            refresh: true,
        ));

        self::assertTrue($result->success);
        self::assertCount(4, $result->candidates);
        self::assertSame('Galata', $result->candidates[2]->name);
        self::assertSame('Kadıköy', $result->candidates[3]->name);
        self::assertSame(DestinationEntityType::DISTRICT, $result->candidates[2]->type);
    }
}
