<?php

declare(strict_types=1);

namespace App\Modules\PublicSite\Controller;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Repository\CityRepository;
use App\Modules\Destination\Repository\CountryRepository;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\TripPlanner\Enum\TripDateMode;
use App\Modules\TripPlanner\Enum\TripPlanningGoal;
use App\Modules\TripPlanner\Form\PublicTripSearchType;
use App\Modules\TripPlanner\Service\ConversationalTravelPlanningService;
use App\Modules\TripPlanner\Service\TravelPlanningService;
use App\Modules\TripPlanner\Service\TravelRecommendationExplanationServiceInterface;
use App\Modules\TripPlanner\ValueObject\ConversationState;
use App\Modules\TripPlanner\ValueObject\TripSearchRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public trip planning entry points.
 *
 * The main /build experience is a chat-first conversation: this controller
 * only reads/writes the session-stored ConversationState and delegates all
 * interpretation and search/comparison logic to
 * ConversationalTravelPlanningService (which itself only ever calls the
 * existing TravelPlanningService — no provider logic here). The structured
 * form from the previous iteration remains available as a secondary
 * "advanced search" fallback at /build/advanced.
 */
final class PublicSiteController extends AbstractController
{
    private const SESSION_KEY = 'trip_planner_conversation';

    #[Route('/', name: 'homepage', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('@PublicSite/home.html.twig');
    }

    #[Route('/build', name: 'public_build', methods: ['GET'])]
    public function build(Request $request): Response
    {
        return $this->render('@PublicSite/build.html.twig', [
            'state' => $this->conversationState($request),
        ]);
    }

    #[Route('/build/message', name: 'public_build_message', methods: ['POST'])]
    public function message(Request $request, ConversationalTravelPlanningService $conversationService): Response
    {
        $message = trim((string) $request->request->get('message', ''));
        $state = $this->conversationState($request);
        if ($message !== '') {
            $state = $conversationService->handleMessage($state, $message);
        }
        $this->storeConversationState($request, $state);

        return $this->redirectToRoute('public_build', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/build/reset', name: 'public_build_reset', methods: ['POST'])]
    public function reset(Request $request): Response
    {
        $request->getSession()->remove(self::SESSION_KEY);

        return $this->redirectToRoute('public_build', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/build/advanced', name: 'public_build_advanced', methods: ['GET', 'POST'])]
    public function advancedSearch(
        Request $request,
        TravelPlanningService $planningService,
        TravelRecommendationExplanationServiceInterface $explanationService,
    ): Response {
        $form = $this->createForm(PublicTripSearchType::class, [
            'dateMode' => TripDateMode::FLEXIBLE,
            'nights' => 5,
            'rooms' => 1,
            'adults' => 2,
            'children' => 0,
            'breakfastPreferred' => true,
            'directFlightPreferred' => false,
            'transferRequired' => false,
            'flightCabin' => FlightCabinClass::ECONOMY,
            'activityCategories' => [],
        ]);
        $form->handleRequest($request);

        $tripRequest = null;
        $result = null;
        $explanation = null;
        $searchFailed = false;

        if ($form->isSubmitted() && $form->isValid()) {
            $tripRequest = $this->tripRequestFromForm($form);
            if ($tripRequest instanceof TripSearchRequest) {
                $result = $planningService->plan($tripRequest);
                $explanation = $explanationService->explain($tripRequest, $result);
            } else {
                $searchFailed = true;
            }
        }

        return $this->render('@PublicSite/build_advanced.html.twig', [
            'form' => $form->createView(),
            'submitted' => $form->isSubmitted(),
            'searchFailed' => $searchFailed,
            'tripRequest' => $tripRequest,
            'result' => $result,
            'explanation' => $explanation,
        ]);
    }

    #[Route('/trips', name: 'public_trips', methods: ['GET'])]
    public function trips(): Response
    {
        return $this->render('@PublicSite/trips.html.twig');
    }

    #[Route('/build/lookup/cities', name: 'public_build_lookup_cities', methods: ['GET'])]
    public function lookupCities(Request $request, CityRepository $cityRepository): JsonResponse
    {
        $query = mb_substr(trim((string) $request->query->get('q', '')), 0, 80);
        if (mb_strlen($query) < 2) {
            return $this->json(['results' => []]);
        }

        return $this->json(['results' => array_map(
            static fn (City $city): array => [
                'id' => $city->getId(),
                'text' => trim($city->getName() . '، ' . ($city->getCountry()?->getName() ?? ''), '، '),
            ],
            $cityRepository->search($query, null, null, 15),
        )]);
    }

    #[Route('/build/lookup/countries', name: 'public_build_lookup_countries', methods: ['GET'])]
    public function lookupCountries(Request $request, CountryRepository $countryRepository): JsonResponse
    {
        $query = mb_substr(trim((string) $request->query->get('q', '')), 0, 80);
        if (mb_strlen($query) < 2) {
            return $this->json(['results' => []]);
        }

        return $this->json(['results' => array_map(
            static fn (Country $country): array => ['id' => $country->getId(), 'text' => $country->getName()],
            $countryRepository->search($query, 15),
        )]);
    }

    private function conversationState(Request $request): ConversationState
    {
        $state = $request->getSession()->get(self::SESSION_KEY);

        return $state instanceof ConversationState ? $state : new ConversationState();
    }

    private function storeConversationState(Request $request, ConversationState $state): void
    {
        $request->getSession()->set(self::SESSION_KEY, $state);
    }

    private function tripRequestFromForm(FormInterface $form): ?TripSearchRequest
    {
        $data = $form->getData();
        if (!\is_array($data)) {
            return null;
        }

        try {
            $dateMode = $data['dateMode'] instanceof TripDateMode ? $data['dateMode'] : TripDateMode::FLEXIBLE;

            return new TripSearchRequest(
                originAirport: $data['originAirport'] ?? null,
                originCity: $data['originCity'] ?? null,
                destinationCity: $data['destinationCity'] ?? null,
                departureDate: $data['departureDate'] ?? null,
                returnDate: $data['returnDate'] ?? null,
                nights: $data['nights'] !== null ? (int) $data['nights'] : null,
                adults: (int) $data['adults'],
                children: (int) $data['children'],
                infants: 0,
                childAges: $data['childrenAges'] ?? [],
                budget: $data['budget'] !== null && $data['budget'] !== '' ? (string) $data['budget'] : null,
                preferredCurrency: 'IRR',
                hotelStarPreference: $data['hotelStarPreference'] !== null && $data['hotelStarPreference'] !== '' ? (int) $data['hotelStarPreference'] : null,
                breakfastPreferred: (bool) ($data['breakfastPreferred'] ?? false),
                flightCabin: $data['flightCabin'] ?? null,
                directFlightPreferred: (bool) ($data['directFlightPreferred'] ?? false),
                activityCategories: array_map(static fn ($category): string => $category instanceof \BackedEnum ? (string) $category->value : (string) $category, $data['activityCategories'] ?? []),
                transferRequired: (bool) ($data['transferRequired'] ?? false),
                dateMode: $dateMode,
                windowStart: $data['windowStart'] ?? null,
                windowEnd: $data['windowEnd'] ?? null,
                goal: TripPlanningGoal::SPECIFIC_DESTINATION,
                rooms: $data['rooms'] !== null ? (int) $data['rooms'] : 1,
                destinationCountry: $data['destinationCountry'] ?? null,
            );
        } catch (\InvalidArgumentException) {
            $form->addError(new FormError('پردازش این جست‌وجو ممکن نشد. لطفاً تاریخ‌ها و تعداد مسافران را بررسی کنید.'));

            return null;
        }
    }
}
