<?php

namespace App\Tests\Modules\Hotel;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Hotel\Provider\HotelSearchProviderInterface;
use App\Modules\Hotel\Service\HotelSearchService;
use App\Modules\Hotel\ValueObject\HotelCandidate;
use App\Modules\Hotel\ValueObject\HotelSearchRequest;
use App\Modules\Hotel\ValueObject\HotelSearchResult;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\SearchSource\Repository\SearchSourceRepository;
use PHPUnit\Framework\TestCase;

class HotelSearchServiceTest extends TestCase
{
    public function testSearchUsesEnabledHotelSourcesInRepositoryPriorityOrder(): void
    {
        $country = (new Country())->setName('Turkey');
        $city = (new City())->setCountry($country)->setName('Istanbul')->setSlug('istanbul');
        $first = $this->source('Booking', 'booking.com', 1, $country);
        $second = $this->source('Hotels', 'hotels.com', 2, null);
        $repository = $this->createMock(SearchSourceRepository::class);
        $repository
            ->expects(self::once())
            ->method('findEnabledForCapability')
            ->with(SearchSource::CAPABILITY_HOTEL, $country)
            ->willReturn([$first, $second]);

        $provider = new class implements HotelSearchProviderInterface {
            /** @var string[] */
            public array $searched = [];

            public function supports(SearchSource $source): bool
            {
                return $source->getProvider() === 'firecrawl';
            }

            public function search(SearchSource $source, HotelSearchRequest $request): HotelSearchResult
            {
                $this->searched[] = $source->getName();

                return HotelSearchResult::success($source, [
                    new HotelCandidate(HotelCandidate::sourceIdentifier($source), $source->getName(), $source->getProvider(), 'id-' . $source->getName(), 'https://' . $source->getDomain(), $source->getName(), $request->query, null, null, 'Turkey', 'Istanbul', null, null, null, null, null, null, null, null),
                ]);
            }
        };

        $summary = (new HotelSearchService([$provider], $repository))->search(new HotelSearchRequest('Arts', $city));

        self::assertSame(['Booking', 'Hotels'], $provider->searched);
        self::assertCount(2, $summary->candidates());
    }

    public function testUnsupportedProviderIsReportedAsFailure(): void
    {
        $country = (new Country())->setName('Turkey');
        $city = (new City())->setCountry($country)->setName('Istanbul')->setSlug('istanbul');
        $source = $this->source('Manual', 'manual.example', 1, $country)->setProvider('manual');
        $repository = $this->createMock(SearchSourceRepository::class);
        $repository->method('findEnabledForCapability')->willReturn([$source]);

        $provider = new class implements HotelSearchProviderInterface {
            public function supports(SearchSource $source): bool
            {
                return false;
            }

            public function search(SearchSource $source, HotelSearchRequest $request): HotelSearchResult
            {
                throw new \LogicException('Unsupported provider should not be called.');
            }
        };

        $summary = (new HotelSearchService([$provider], $repository))->search(new HotelSearchRequest('Arts', $city));

        self::assertTrue($summary->hasFailures());
        self::assertSame([], $summary->candidates());
    }

    private function source(string $name, string $domain, int $priority, ?Country $country): SearchSource
    {
        return (new SearchSource())
            ->setName($name)
            ->setDomain($domain)
            ->setProvider('firecrawl')
            ->setProviderType(SearchSourceProviderType::FIRECRAWL)
            ->setCapabilities([SearchSource::CAPABILITY_HOTEL])
            ->setPriority($priority)
            ->setCountry($country);
    }
}
