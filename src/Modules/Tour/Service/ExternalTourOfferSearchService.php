<?php

namespace App\Modules\Tour\Service;

use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Repository\SearchSourceRepository;
use App\Modules\Tour\Provider\ExternalTourOfferProviderInterface;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchRequest;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchResult;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchSummary;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class ExternalTourOfferSearchService
{
    /**
     * @var ExternalTourOfferProviderInterface[]
     */
    private array $providers;

    /**
     * @param iterable<ExternalTourOfferProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('external_tour_offer.provider')] iterable $providers,
        private readonly SearchSourceRepository $searchSourceRepository,
        private readonly TourSourceEligibilityService $eligibilityService,
    ) {
        $this->providers = \is_array($providers) ? $providers : iterator_to_array($providers);
    }

    public function search(ExternalTourOfferSearchRequest $request): ExternalTourOfferSearchSummary
    {
        $results = [];
        foreach ($this->searchSourceRepository->findEnabledForCapability(SearchSource::CAPABILITY_TOUR) as $source) {
            $eligibility = $this->eligibilityService->evaluate($source, $request);
            if (!$eligibility->shouldSearch()) {
                $results[] = ExternalTourOfferSearchResult::skipped($source, [
                    'eligibility' => $eligibility->status->value,
                    'eligibilityReasons' => $eligibility->reasons,
                    'accessStrategy' => $source->getConfig()['accessStrategy'] ?? $source->getProvider(),
                    'rawResultCount' => 0,
                    'acceptedCandidateCount' => 0,
                    'rejectedCandidateCount' => 0,
                ]);
                continue;
            }

            $provider = $this->providerFor($source);
            $result = $provider instanceof ExternalTourOfferProviderInterface
                ? $provider->search($source, $request)
                : ExternalTourOfferSearchResult::failure($source, [sprintf('No external tour offer provider is registered for source provider "%s".', $source->getProvider())]);
            $results[] = $result->withMetadata([
                'eligibility' => $eligibility->status->value,
                'eligibilityReasons' => $eligibility->reasons,
                'accessStrategy' => $source->getConfig()['accessStrategy'] ?? $source->getProvider(),
            ]);
        }

        return new ExternalTourOfferSearchSummary($results);
    }

    private function providerFor(SearchSource $source): ?ExternalTourOfferProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($source)) {
                return $provider;
            }
        }

        return null;
    }
}
