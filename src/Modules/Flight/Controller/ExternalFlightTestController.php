<?php

namespace App\Modules\Flight\Controller;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Flight\Form\ExternalFlightTestType;
use App\Modules\Flight\Service\FlightOfferSearchService;
use App\Modules\Flight\Service\FlightOfferStoreService;
use App\Modules\Flight\ValueObject\FlightOfferSearchRequest;
use App\Modules\Flight\ValueObject\FlightOfferSearchSummary;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Repository\SearchSourceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/flight-commerce/external-test')]
class ExternalFlightTestController extends AbstractController
{
    #[Route('/', name: 'flight_external_test', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        FlightOfferSearchService $searchService,
        FlightOfferStoreService $storeService,
        SearchSourceRepository $sourceRepository,
    ): Response {
        $form = $this->createForm(ExternalFlightTestType::class, [
            'departureDate' => new \DateTimeImmutable('+30 days'),
            'returnDate' => null,
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'cabinClass' => \App\Modules\Flight\Enum\FlightCabinClass::ECONOMY,
            'directOnly' => false,
        ]);
        $form->handleRequest($request);

        $summary = null;
        $searchRequest = null;
        $stored = 0;
        $hasSources = $sourceRepository->findEnabledForCapability(SearchSource::CAPABILITY_FLIGHT) !== [];

        if ($form->isSubmitted() && $form->isValid()) {
            $searchRequest = $this->searchRequestFromForm($form);
            if ($searchRequest instanceof FlightOfferSearchRequest) {
                $summary = $searchService->search($searchRequest);
                $stored = $storeService->storeSummary($searchRequest, $summary);
            }
        }

        return $this->render('@Flight/external_test/index.html.twig', [
            'form' => $form->createView(),
            'summary' => $summary,
            'searchRequest' => $searchRequest,
            'stored' => $stored,
            'hasSources' => $hasSources,
        ]);
    }

    private function searchRequestFromForm(FormInterface $form): ?FlightOfferSearchRequest
    {
        $data = $form->getData();
        if (!\is_array($data) || !$data['originAirport'] instanceof Airport || !$data['destinationAirport'] instanceof Airport) {
            return null;
        }

        try {
            return new FlightOfferSearchRequest(
                originAirport: $data['originAirport'],
                destinationAirport: $data['destinationAirport'],
                departureDate: $data['departureDate'],
                returnDate: $data['returnDate'] ?? null,
                adults: (int) $data['adults'],
                children: (int) $data['children'],
                infants: (int) $data['infants'],
                cabinClass: $data['cabinClass'],
                directOnly: (bool) ($data['directOnly'] ?? false),
            );
        } catch (\InvalidArgumentException $exception) {
            $form->addError(new \Symfony\Component\Form\FormError($exception->getMessage()));

            return null;
        }
    }
}
