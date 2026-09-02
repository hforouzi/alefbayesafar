<?php

namespace App\Modules\Tour\Controller;

use App\Modules\Destination\Entity\City;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Repository\SearchSourceRepository;
use App\Modules\Tour\Enum\ExternalTourOfferSearchStatus;
use App\Modules\Tour\Repository\ExternalTourOfferRepository;
use App\Modules\Tour\Form\ExternalTourTestType;
use App\Modules\Tour\Service\ExternalTourOfferSearchService;
use App\Modules\Tour\Service\ExternalTourOfferStoreService;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchSummary;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/tour-commerce/external-test')]
class ExternalTourTestController extends AbstractController
{
    #[Route('/', name: 'tour_external_test', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, ExternalTourOfferSearchService $searchService, ExternalTourOfferStoreService $storeService, SearchSourceRepository $sourceRepository, ExternalTourOfferRepository $externalOfferRepository): Response
    {
        $form = $this->createForm(ExternalTourTestType::class, [
            'nights' => 5,
            'rooms' => 1,
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'currency' => 'EUR',
        ]);
        $form->handleRequest($request);

        $summary = null;
        $searchRequest = null;
        $stored = 0;
        $storedBySource = [];
        $storedOffers = [];
        $searchState = null;
        $orderedResults = [];
        $hasSources = $sourceRepository->findEnabledForCapability(SearchSource::CAPABILITY_TOUR) !== [];

        if ($form->isSubmitted() && !$form->isValid()) {
            $searchState = 'invalid_request';
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $searchRequest = $this->searchRequestFromForm($form);
            if ($searchRequest instanceof ExternalTourOfferSearchRequest) {
                $summary = $searchService->search($searchRequest);
                foreach ($summary->getResults() as $result) {
                    $storedForSource = $storeService->storeResult($searchRequest, $result);
                    $storedBySource[(int) $result->source->getId()] = $storedForSource;
                    $stored += $storedForSource;
                }
                $orderedResults = $summary->getResults();
                usort($orderedResults, static function ($left, $right): int {
                    $rank = static fn ($result): int => match ($result->status) {
                        ExternalTourOfferSearchStatus::OFFERS_FOUND => 0,
                        ExternalTourOfferSearchStatus::NO_RESULTS => 1,
                        ExternalTourOfferSearchStatus::NO_DATA => 2,
                        ExternalTourOfferSearchStatus::PROVIDER_ERROR => 3,
                        ExternalTourOfferSearchStatus::SKIPPED => 4,
                    };

                    return $rank($left) <=> $rank($right);
                });
                $storedOffers = $externalOfferRepository->findFreshPotentialMatches($searchRequest, new \DateTimeImmutable());
                $searchState = $this->searchState($summary, $hasSources);
            } else {
                $searchState = 'invalid_request';
            }
        }

        return $this->render('@Tour/external_test/index.html.twig', [
            'form' => $form->createView(),
            'summary' => $summary,
            'orderedResults' => $orderedResults,
            'searchRequest' => $searchRequest,
            'stored' => $stored,
            'storedBySource' => $storedBySource,
            'storedOffers' => $storedOffers,
            'searchState' => $searchState,
            'submitted' => $form->isSubmitted(),
            'hasSources' => $hasSources,
        ]);
    }

    private function searchState(ExternalTourOfferSearchSummary $summary, bool $hasSources): string
    {
        if (!$hasSources) {
            return 'no_sources';
        }

        $results = $summary->getResults();
        if ($results === []) {
            return 'no_sources';
        }

        $hasOffers = false;
        $hasFailure = false;
        $allSkipped = true;
        $allNoResults = true;
        foreach ($results as $result) {
            $hasOffers = $hasOffers || $result->status === ExternalTourOfferSearchStatus::OFFERS_FOUND;
            $hasFailure = $hasFailure || \in_array($result->status, [ExternalTourOfferSearchStatus::NO_DATA, ExternalTourOfferSearchStatus::PROVIDER_ERROR], true);
            $allSkipped = $allSkipped && $result->status === ExternalTourOfferSearchStatus::SKIPPED;
            $allNoResults = $allNoResults && $result->status === ExternalTourOfferSearchStatus::NO_RESULTS;
        }

        if ($hasOffers && $hasFailure) {
            return 'partial_success';
        }
        if ($hasOffers) {
            return 'success';
        }
        if ($allSkipped) {
            return 'no_eligible_sources';
        }
        if ($allNoResults) {
            return 'no_results';
        }

        return $summary->hasStatus(ExternalTourOfferSearchStatus::PROVIDER_ERROR) ? 'provider_error' : 'no_data';
    }

    private function searchRequestFromForm(FormInterface $form): ?ExternalTourOfferSearchRequest
    {
        $data = $form->getData();
        if (!\is_array($data) || !$data['destinationCity'] instanceof City) {
            return null;
        }

        try {
            return new ExternalTourOfferSearchRequest(
                originAirport: $data['originAirport'] ?? null,
                destinationCity: $data['destinationCity'],
                departureDate: $data['departureDate'] ?? null,
                returnDate: $data['returnDate'] ?? null,
                validFrom: $data['validFrom'] ?? null,
                validTo: $data['validTo'] ?? null,
                nights: $data['nights'] !== null ? (int) $data['nights'] : null,
                adults: (int) $data['adults'],
                children: (int) $data['children'],
                infants: (int) $data['infants'],
                childrenAges: $data['childrenAges'] ?? [],
                budget: $data['budget'] !== null && $data['budget'] !== '' ? (string) $data['budget'] : null,
                currency: $data['currency'] !== null && $data['currency'] !== '' ? strtoupper((string) $data['currency']) : null,
                rooms: (int) $data['rooms'],
            );
        } catch (\InvalidArgumentException $exception) {
            $form->addError(new FormError($exception->getMessage()));

            return null;
        }
    }
}
