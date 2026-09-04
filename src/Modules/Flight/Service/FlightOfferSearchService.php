<?php

namespace App\Modules\Flight\Service;

use App\Modules\Flight\Provider\FlightOfferProviderInterface;
use App\Modules\Flight\ValueObject\FlightOfferSearchRequest;
use App\Modules\Flight\ValueObject\FlightOfferSearchResult;
use App\Modules\Flight\ValueObject\FlightOfferSearchSummary;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Repository\SearchSourceRepository;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class FlightOfferSearchService
{
    /**
     * @var FlightOfferProviderInterface[]
     */
    private array $providers;

    /**
     * @param iterable<FlightOfferProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('flight_offer.provider')] iterable $providers,
        private readonly SearchSourceRepository $searchSourceRepository,
    ) {
        $this->providers = \is_array($providers) ? $providers : iterator_to_array($providers);
    }

    public function search(FlightOfferSearchRequest $request): FlightOfferSearchSummary
    {
        $sources = $this->searchSourceRepository->findEnabledForCapability(SearchSource::CAPABILITY_FLIGHT);
        $results = [];

        foreach ($sources as $source) {
            $provider = $this->providerFor($source);
            if (!$provider instanceof FlightOfferProviderInterface) {
                $results[] = FlightOfferSearchResult::failure($source, [sprintf('No flight offer provider is registered for source provider "%s".', $source->getProvider())]);
                continue;
            }

            $results[] = $provider->search($source, $request);
        }

        return new FlightOfferSearchSummary($results);
    }

    private function providerFor(SearchSource $source): ?FlightOfferProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($source)) {
                return $provider;
            }
        }

        return null;
    }
}
