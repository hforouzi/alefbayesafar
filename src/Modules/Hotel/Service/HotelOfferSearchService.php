<?php

namespace App\Modules\Hotel\Service;

use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Provider\HotelOfferProviderInterface;
use App\Modules\Hotel\ValueObject\HotelOfferSearchRequest;
use App\Modules\Hotel\ValueObject\HotelOfferSearchResult;
use App\Modules\Hotel\ValueObject\HotelOfferSearchSummary;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Repository\SearchSourceRepository;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class HotelOfferSearchService
{
    /**
     * @var HotelOfferProviderInterface[]
     */
    private array $providers;

    /**
     * @param iterable<HotelOfferProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('hotel_offer.provider')] iterable $providers,
        private readonly SearchSourceRepository $searchSourceRepository,
    ) {
        $this->providers = \is_array($providers) ? $providers : iterator_to_array($providers);
    }

    public function search(Hotel $hotel, HotelOfferSearchRequest $request): HotelOfferSearchSummary
    {
        $sources = $this->searchSourceRepository->findEnabledForCapability(SearchSource::CAPABILITY_HOTEL, $hotel->getCity()?->getCountry());
        $results = [];

        foreach ($sources as $source) {
            $provider = $this->providerFor($source);
            if (!$provider instanceof HotelOfferProviderInterface) {
                $results[] = HotelOfferSearchResult::failure($source, [sprintf('No hotel offer provider is registered for source provider "%s".', $source->getProvider())]);
                continue;
            }

            $results[] = $provider->search($source, $hotel, $request);
        }
        return new HotelOfferSearchSummary($results);
    }

    private function providerFor(SearchSource $source): ?HotelOfferProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($source)) {
                return $provider;
            }
        }

        return null;
    }
}
