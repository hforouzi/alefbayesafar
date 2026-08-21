<?php

namespace App\Tests\Modules\SearchSource;

use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use PHPUnit\Framework\TestCase;

class SearchSourceEntityTest extends TestCase
{
    public function testDomainProviderCapabilitiesAndProviderTypeNormalize(): void
    {
        $source = (new SearchSource())
            ->setName('Booking Firecrawl')
            ->setDomain('https://Example.COM/hotels/')
            ->setProvider('Fire Crawl')
            ->setProviderType(SearchSourceProviderType::FIRECRAWL)
            ->setCapabilities(['hotel', 'hotel', ' REVIEW ', '']);

        self::assertSame('example.com/hotels', $source->getDomain());
        self::assertSame('fire_crawl', $source->getProvider());
        self::assertSame(SearchSourceProviderType::FIRECRAWL, $source->getProviderType());
        self::assertSame(['hotel', 'review'], $source->getCapabilities());
        self::assertTrue($source->supports('hotel'));
        self::assertTrue($source->supports('review'));
        self::assertFalse($source->supports('flight'));
    }

    public function testConfigRejectsSecretLikeKeys(): void
    {
        $source = (new SearchSource())
            ->setName('Unsafe')
            ->setDomain('example.com')
            ->setConfig(['headers' => ['api_key' => 'secret']]);

        $this->expectException(\LogicException::class);
        $source->assertConfigContainsNoSecrets();
    }

    public function testNonSensitiveConfigIsAllowed(): void
    {
        $source = (new SearchSource())
            ->setName('Safe')
            ->setDomain('example.com')
            ->setConfig(['maxDepth' => 2, 'locale' => 'fa']);

        $source->assertConfigContainsNoSecrets();

        self::assertSame(['maxDepth' => 2, 'locale' => 'fa'], $source->getConfig());
    }
}
