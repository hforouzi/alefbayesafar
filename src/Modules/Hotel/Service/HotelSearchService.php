<?php

namespace App\Modules\Hotel\Service;

use App\Modules\Hotel\Provider\HotelSearchProviderInterface;
use App\Modules\Hotel\ValueObject\HotelSearchRequest;
use App\Modules\Hotel\ValueObject\HotelSearchResult;
use App\Modules\Hotel\ValueObject\HotelSearchSummary;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Repository\SearchSourceRepository;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class HotelSearchService
{
    /**
     * @var HotelSearchProviderInterface[]
     */
    private array $providers;

    /**
     * @param iterable<HotelSearchProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('hotel_search.provider')] iterable $providers,
        private readonly SearchSourceRepository $searchSourceRepository,
    ) {
        $this->providers = \is_array($providers) ? $providers : iterator_to_array($providers);
    }

    public function search(HotelSearchRequest $request): HotelSearchSummary
    {
        $sources = $this->searchSourceRepository->findEnabledForCapability(SearchSource::CAPABILITY_HOTEL, $request->city->getCountry());
        $results = [];

        foreach ($sources as $source) {
            $provider = $this->providerFor($source);
            if (!$provider instanceof HotelSearchProviderInterface) {
                $results[] = HotelSearchResult::failure($source, [sprintf('No hotel search provider is registered for source provider "%s".', $source->getProvider())]);
                continue;
            }

            $results[] = $provider->search($source, $request);
        }

        return new HotelSearchSummary($results);
    }

    private function providerFor(SearchSource $source): ?HotelSearchProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($source)) {
                return $provider;
            }
        }

        return null;
    }
}
