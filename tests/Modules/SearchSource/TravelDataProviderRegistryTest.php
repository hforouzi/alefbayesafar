<?php

namespace App\Tests\Modules\SearchSource;

use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\SearchSource\Provider\FirecrawlProvider;
use App\Modules\SearchSource\Provider\ProviderConfigurationException;
use App\Modules\SearchSource\Provider\TravelDataProviderRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TravelDataProviderRegistryTest extends KernelTestCase
{
    public function testRegistryDiscoversFirecrawlProvider(): void
    {
        self::bootKernel();
        $registry = self::getContainer()->get(TravelDataProviderRegistry::class);
        self::assertInstanceOf(TravelDataProviderRegistry::class, $registry);

        $provider = $registry->get('firecrawl');

        self::assertInstanceOf(FirecrawlProvider::class, $provider);
        self::assertSame('Firecrawl', $provider->getLabel());
        self::assertArrayHasKey('firecrawl', $registry->all());
    }

    public function testUnknownProviderFailsClearly(): void
    {
        self::bootKernel();
        $registry = self::getContainer()->get(TravelDataProviderRegistry::class);
        self::assertInstanceOf(TravelDataProviderRegistry::class, $registry);

        $this->expectException(ProviderConfigurationException::class);
        $registry->get('missing-provider');
    }

    public function testFirecrawlSupportRequiresMatchingProviderTypeAndCapability(): void
    {
        self::bootKernel();
        $provider = self::getContainer()->get(TravelDataProviderRegistry::class)->get('firecrawl');
        $source = (new SearchSource())
            ->setProvider('firecrawl')
            ->setProviderType(SearchSourceProviderType::FIRECRAWL)
            ->setCapabilities([SearchSource::CAPABILITY_HOTEL]);

        self::assertTrue($provider->supports($source, SearchSource::CAPABILITY_HOTEL));
        self::assertFalse($provider->supports($source, SearchSource::CAPABILITY_REVIEW));
    }
}
