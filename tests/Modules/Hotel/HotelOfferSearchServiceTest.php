<?php

namespace App\Tests\Modules\Hotel;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Provider\HotelOfferProviderInterface;
use App\Modules\Hotel\Service\HotelOfferSearchService;
use App\Modules\Hotel\ValueObject\HotelOfferCandidate;
use App\Modules\Hotel\ValueObject\HotelOfferSearchRequest;
use App\Modules\Hotel\ValueObject\HotelOfferSearchResult;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\SearchSource\Repository\SearchSourceRepository;
use PHPUnit\Framework\TestCase;

class HotelOfferSearchServiceTest extends TestCase
{
    public function testSearchUsesEnabledHotelSourcesForHotelCountry(): void
    {
        $country = (new Country())->setName('Turkey');
        $hotel = $this->hotel($country);
        $first = $this->source('Booking', 'booking.com', 1, $country);
        $second = $this->source('Hotels', 'hotels.com', 2, null);
        $repository = $this->createMock(SearchSourceRepository::class);
        $repository
            ->expects(self::once())
            ->method('findEnabledForCapability')
            ->with(SearchSource::CAPABILITY_HOTEL, $country)
            ->willReturn([$first, $second]);

        $provider = new class implements HotelOfferProviderInterface {
            /** @var string[] */
            public array $searched = [];

            public function supports(SearchSource $source): bool
            {
                return $source->getProvider() === 'firecrawl';
            }

            public function search(SearchSource $source, Hotel $hotel, HotelOfferSearchRequest $request): HotelOfferSearchResult
            {
                $this->searched[] = $source->getName();

                return HotelOfferSearchResult::success($source, [
                    new HotelOfferCandidate(HotelOfferCandidate::sourceIdentifier($source), $source->getName(), $source->getProvider(), 'offer-' . $source->getName(), null, null, 'EUR', '620.00', 'https://' . $source->getDomain(), HotelOfferCandidate::sourceIdentifier($source), ['nights' => $request->checkOut->diff($request->checkIn)->days]),
                ], ['provider' => $source->getProvider()]);
            }
        };

        $summary = (new HotelOfferSearchService([$provider], $repository))->search($hotel, $this->request());

        self::assertSame(['Booking', 'Hotels'], $provider->searched);
        self::assertCount(2, $summary->getResults());
        self::assertCount(2, $summary->getCandidates());
        self::assertFalse($summary->hasFailures());
    }

    public function testProviderFailureRemainsFailureAndAggregationContinues(): void
    {
        $country = (new Country())->setName('Turkey');
        $successSource = $this->source('Booking', 'booking.com', 1, $country);
        $failureSource = $this->source('Agoda', 'agoda.com', 2, $country);
        $repository = $this->createMock(SearchSourceRepository::class);
        $repository->method('findEnabledForCapability')->willReturn([$successSource, $failureSource]);

        $provider = new class implements HotelOfferProviderInterface {
            public function supports(SearchSource $source): bool
            {
                return true;
            }

            public function search(SearchSource $source, Hotel $hotel, HotelOfferSearchRequest $request): HotelOfferSearchResult
            {
                if ($source->getName() === 'Agoda') {
                    return HotelOfferSearchResult::failure($source, ['Provider timeout'], ['provider' => $source->getProvider()]);
                }

                return HotelOfferSearchResult::success($source, [
                    new HotelOfferCandidate(HotelOfferCandidate::sourceIdentifier($source), $source->getName(), $source->getProvider(), 'offer-1', null, null, 'EUR', '620.00', null, null),
                ]);
            }
        };

        $summary = (new HotelOfferSearchService([$provider], $repository))->search($this->hotel($country), $this->request());

        self::assertTrue($summary->hasFailures());
        self::assertCount(1, $summary->getCandidates());
        self::assertSame(['Provider timeout'], $summary->getResults()[1]->errors);
    }

    public function testMissingProviderCreatesExplicitFailure(): void
    {
        $country = (new Country())->setName('Turkey');
        $source = $this->source('Manual', 'manual.example', 1, $country)->setProvider('manual');
        $repository = $this->createMock(SearchSourceRepository::class);
        $repository->method('findEnabledForCapability')->willReturn([$source]);

        $provider = new class implements HotelOfferProviderInterface {
            public function supports(SearchSource $source): bool
            {
                return false;
            }

            public function search(SearchSource $source, Hotel $hotel, HotelOfferSearchRequest $request): HotelOfferSearchResult
            {
                throw new \LogicException('Unsupported provider should not be called.');
            }
        };

        $summary = (new HotelOfferSearchService([$provider], $repository))->search($this->hotel($country), $this->request());

        self::assertTrue($summary->hasFailures());
        self::assertSame([], $summary->getCandidates());
        self::assertSame('No hotel offer provider is registered for source provider "manual".', $summary->getResults()[0]->errors[0]);
    }

    public function testEmptySuccessIsDifferentFromFailure(): void
    {
        $country = (new Country())->setName('Turkey');
        $source = $this->source('Booking', 'booking.com', 1, $country);
        $repository = $this->createMock(SearchSourceRepository::class);
        $repository->method('findEnabledForCapability')->willReturn([$source]);

        $provider = new class implements HotelOfferProviderInterface {
            public function supports(SearchSource $source): bool
            {
                return true;
            }

            public function search(SearchSource $source, Hotel $hotel, HotelOfferSearchRequest $request): HotelOfferSearchResult
            {
                return HotelOfferSearchResult::success($source, [], ['count' => 0]);
            }
        };

        $summary = (new HotelOfferSearchService([$provider], $repository))->search($this->hotel($country), $this->request());

        self::assertFalse($summary->hasFailures());
        self::assertSame([], $summary->getCandidates());
        self::assertSame(['count' => 0], $summary->getResults()[0]->metadata);
    }

    private function hotel(Country $country): Hotel
    {
        return (new Hotel())
            ->setName('Arts Hotel Istanbul')
            ->setSlug('arts-hotel-istanbul')
            ->setCity((new City())->setCountry($country)->setName('Istanbul')->setSlug('istanbul'));
    }

    private function request(): HotelOfferSearchRequest
    {
        return new HotelOfferSearchRequest(
            new \DateTimeImmutable('2026-09-10'),
            new \DateTimeImmutable('2026-09-15'),
            2,
            1,
            [4],
        );
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
