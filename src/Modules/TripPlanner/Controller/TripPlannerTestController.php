<?php

namespace App\Modules\TripPlanner\Controller;

use App\Modules\Destination\Entity\City;
use App\Modules\TripPlanner\Form\TripPlannerTestType;
use App\Modules\TripPlanner\Service\TripPlanner;
use App\Modules\TripPlanner\ValueObject\TripPlanResult;
use App\Modules\TripPlanner\ValueObject\TripSearchRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/trip-planner/test')]
class TripPlannerTestController extends AbstractController
{
    #[Route('/', name: 'trip_planner_test', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, TripPlanner $planner): Response
    {
        $form = $this->createForm(TripPlannerTestType::class, [
            'departureDate' => new \DateTimeImmutable('+30 days'),
            'nights' => 5,
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'preferredCurrency' => 'EUR',
            'flightCabin' => \App\Modules\Flight\Enum\FlightCabinClass::ECONOMY,
            'breakfastPreferred' => true,
        ]);
        $form->handleRequest($request);

        $tripRequest = null;
        $result = null;
        $state = null;

        if ($form->isSubmitted() && !$form->isValid()) {
            $state = 'invalid_request';
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $tripRequest = $this->tripRequestFromForm($form);
            if ($tripRequest instanceof TripSearchRequest) {
                $result = $planner->plan($tripRequest);
                $state = $result->status->value;
            } else {
                $state = 'invalid_request';
            }
        }

        return $this->render('@TripPlanner/test/index.html.twig', [
            'form' => $form->createView(),
            'tripRequest' => $tripRequest,
            'result' => $result,
            'state' => $state,
            'submitted' => $form->isSubmitted(),
        ]);
    }

    private function tripRequestFromForm(FormInterface $form): ?TripSearchRequest
    {
        $data = $form->getData();
        if (!\is_array($data) || !$data['destinationCity'] instanceof City || !$data['departureDate'] instanceof \DateTimeImmutable) {
            return null;
        }

        try {
            return new TripSearchRequest(
                originAirport: $data['originAirport'] ?? null,
                originCity: $data['originCity'] ?? null,
                destinationCity: $data['destinationCity'],
                departureDate: $data['departureDate'],
                returnDate: $data['returnDate'] ?? null,
                nights: $data['nights'] !== null ? (int) $data['nights'] : null,
                adults: (int) $data['adults'],
                children: (int) $data['children'],
                infants: (int) $data['infants'],
                childAges: $data['childrenAges'] ?? [],
                budget: $data['budget'] !== null && $data['budget'] !== '' ? (string) $data['budget'] : null,
                preferredCurrency: strtoupper((string) $data['preferredCurrency']),
                hotelStarPreference: $data['hotelStarPreference'] !== null && $data['hotelStarPreference'] !== '' ? (int) $data['hotelStarPreference'] : null,
                breakfastPreferred: (bool) ($data['breakfastPreferred'] ?? false),
                flightCabin: $data['flightCabin'],
                directFlightPreferred: (bool) ($data['directFlightPreferred'] ?? false),
                activityCategories: array_map(static fn ($category): string => $category instanceof \BackedEnum ? (string) $category->value : (string) $category, $data['activityCategories'] ?? []),
                transferRequired: (bool) ($data['transferRequired'] ?? false),
            );
        } catch (\InvalidArgumentException $exception) {
            $form->addError(new FormError($exception->getMessage()));

            return null;
        }
    }
}
