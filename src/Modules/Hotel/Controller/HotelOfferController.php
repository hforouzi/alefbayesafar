<?php

namespace App\Modules\Hotel\Controller;

use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Form\HotelOfferSearchType;
use App\Modules\Hotel\Repository\HotelOfferRepository;
use App\Modules\Hotel\Service\HotelOfferSearchService;
use App\Modules\Hotel\Service\HotelOfferStoreService;
use App\Modules\Hotel\ValueObject\HotelOfferSearchRequest;
use App\Modules\Hotel\ValueObject\HotelOfferSearchSummary;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Repository\SearchSourceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/catalog/hotels/{id}/offers', requirements: ['id' => '\d+'])]
class HotelOfferController extends AbstractController
{
    #[Route('', name: 'hotel_offer_search', methods: ['GET', 'POST'])]
    public function search(
        Request $request,
        Hotel $hotel,
        HotelOfferSearchService $searchService,
        HotelOfferStoreService $storeService,
        HotelOfferRepository $offerRepository,
        SearchSourceRepository $searchSourceRepository,
        TranslatorInterface $translator,
    ): Response {
        $form = $this->createForm(HotelOfferSearchType::class);
        $form->handleRequest($request);
        $summary = null;
        $searchRequest = null;
        $storedCount = null;
        $contextOffers = [];
        $searchPerformed = false;
        $searchServiceCalled = false;
        $storeServiceCalled = false;
        $sourceDiagnostics = [];
        $rawSubmittedData = $this->rawSubmittedData($request, $form);
        $visibleSubmittedDates = $request->request->all('hotel_offer_search_visible');
        $gregorianSubmittedDates = $request->request->all('hotel_offer_search_submitted');

        if ($form->isSubmitted()) {
            $sourceDiagnostics = $this->sourceDiagnostics(
                $searchSourceRepository->findEnabledForCapability(SearchSource::CAPABILITY_HOTEL, $hotel->getCity()?->getCountry()),
            );
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $searchRequest = $this->searchRequestFromForm($form, $translator);
            if ($searchRequest instanceof HotelOfferSearchRequest) {
                $searchPerformed = true;
                $searchServiceCalled = true;
                $summary = $searchService->search($hotel, $searchRequest);
                $storeServiceCalled = true;
                $storedCount = $storeService->storeSummary($hotel, $searchRequest, $summary);
                $contextOffers = $offerRepository->findForSearchContext($hotel, $searchRequest);
            }
        }
        $formErrors = $this->formErrors($form);

        return $this->render('@Hotel/hotel_offer/search.html.twig', [
            'hotel' => $hotel,
            'form' => $form->createView(),
            'summary' => $summary,
            'searchRequest' => $searchRequest,
            'searchPerformed' => $searchPerformed,
            'storedCount' => $storedCount,
            'contextOffers' => $contextOffers,
            'recentOffers' => $offerRepository->findRecentForHotel($hotel),
            'formSubmitted' => $form->isSubmitted(),
            'formValid' => $form->isSubmitted() ? $form->isValid() && $searchRequest instanceof HotelOfferSearchRequest : null,
            'formErrors' => $formErrors,
            'developerDiagnosticsEnabled' => $this->shouldShowDiagnostics(),
            'diagnostics' => [
                'kernel' => [
                    'environment' => $this->getParameter('kernel.environment'),
                    'debug' => (bool) $this->getParameter('kernel.debug'),
                ],
                'form' => [
                    'submitted' => $form->isSubmitted(),
                    'valid' => $form->isSubmitted() ? $form->isValid() && $searchRequest instanceof HotelOfferSearchRequest : null,
                    'rawSubmittedData' => $rawSubmittedData,
                    'normalizedData' => $this->normalizedDiagnostics($form, $searchRequest),
                    'visibleJalaliCheckIn' => $visibleSubmittedDates['checkIn'] ?? null,
                    'submittedGregorianCheckIn' => $rawSubmittedData['checkIn'] ?? $gregorianSubmittedDates['checkIn'] ?? null,
                    'visibleJalaliCheckOut' => $visibleSubmittedDates['checkOut'] ?? null,
                    'submittedGregorianCheckOut' => $rawSubmittedData['checkOut'] ?? $gregorianSubmittedDates['checkOut'] ?? null,
                    'errors' => $formErrors,
                ],
                'controller' => [
                    'controllerReached' => true,
                    'searchPerformed' => $searchPerformed,
                    'searchServiceCalled' => $searchServiceCalled,
                    'storeServiceCalled' => $storeServiceCalled,
                    'storedCount' => $storedCount,
                ],
                'search' => [
                    'hotel' => [
                        'id' => $hotel->getId(),
                        'name' => $hotel->getName(),
                    ],
                    'sourceCount' => \count($sourceDiagnostics),
                    'sources' => $sourceDiagnostics,
                ],
            ],
        ]);
    }

    private function searchRequestFromForm(FormInterface $form, TranslatorInterface $translator): ?HotelOfferSearchRequest
    {
        $data = $form->getData();
        $checkIn = $this->dateFromCanonical($data['checkIn'] ?? null);
        $checkOut = $this->dateFromCanonical($data['checkOut'] ?? null);
        $childrenAges = $this->childrenAgesFromText($data['childrenAges'] ?? null);

        if (!$checkIn instanceof \DateTimeImmutable) {
            $form->get('checkIn')->addError(new FormError($translator->trans('hotel.offer.validation.invalid_date')));

            return null;
        }

        if (!$checkOut instanceof \DateTimeImmutable) {
            $form->get('checkOut')->addError(new FormError($translator->trans('hotel.offer.validation.invalid_date')));

            return null;
        }

        if ($childrenAges === null) {
            $form->get('childrenAges')->addError(new FormError($translator->trans('hotel.offer.validation.child_ages_numbers')));

            return null;
        }

        try {
            return new HotelOfferSearchRequest(
                $checkIn,
                $checkOut,
                (int) $data['adults'],
                (int) $data['children'],
                $childrenAges,
            );
        } catch (\InvalidArgumentException $exception) {
            $field = str_contains($exception->getMessage(), 'child age') || str_contains($exception->getMessage(), 'one age')
                ? 'childrenAges'
                : 'checkOut';
            $message = $field === 'childrenAges'
                ? 'hotel.offer.validation.child_ages_count'
                : 'hotel.offer.validation.check_out_after_check_in';
            $form->get($field)->addError(new FormError($translator->trans($message)));

            return null;
        }
    }

    private function dateFromCanonical(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        return $date instanceof \DateTimeImmutable && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            ? $date
            : null;
    }

    /**
     * @return int[]|null
     */
    private function childrenAgesFromText(mixed $value): ?array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $ages = [];
        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            if (preg_match('/^\d+$/', $part) !== 1) {
                return null;
            }

            $ages[] = (int) $part;
        }

        return $ages;
    }

    /**
     * @return array<string, mixed>
     */
    private function rawSubmittedData(Request $request, FormInterface $form): array
    {
        return $request->request->all($form->getName());
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedDiagnostics(FormInterface $form, ?HotelOfferSearchRequest $searchRequest): array
    {
        if ($searchRequest instanceof HotelOfferSearchRequest) {
            return [
                'checkIn' => $searchRequest->checkIn->format('Y-m-d'),
                'checkOut' => $searchRequest->checkOut->format('Y-m-d'),
                'adults' => $searchRequest->adults,
                'children' => $searchRequest->children,
                'childrenAges' => $searchRequest->childrenAges,
            ];
        }

        $data = $form->getData();
        if (!\is_array($data)) {
            return [];
        }

        return [
            'checkIn' => $this->dateFromCanonical($data['checkIn'] ?? null)?->format('Y-m-d'),
            'checkOut' => $this->dateFromCanonical($data['checkOut'] ?? null)?->format('Y-m-d'),
            'adults' => isset($data['adults']) ? (int) $data['adults'] : null,
            'children' => isset($data['children']) ? (int) $data['children'] : null,
            'childrenAges' => $this->childrenAgesFromText($data['childrenAges'] ?? null),
        ];
    }

    /**
     * @return array<int, array{field: string, message: string}>
     */
    private function formErrors(FormInterface $form, string $prefix = ''): array
    {
        $errors = [];
        foreach ($form->getErrors() as $error) {
            $errors[] = [
                'field' => $prefix !== '' ? $prefix : $form->getName(),
                'message' => $error->getMessage(),
            ];
        }

        foreach ($form->all() as $name => $child) {
            $childPrefix = $prefix !== '' ? $prefix . '.' . $name : $name;
            array_push($errors, ...$this->formErrors($child, $childPrefix));
        }

        return $errors;
    }

    /**
     * @param SearchSource[] $sources
     *
     * @return array<int, array<string, mixed>>
     */
    private function sourceDiagnostics(array $sources): array
    {
        return array_map(static fn (SearchSource $source): array => [
            'id' => $source->getId(),
            'name' => $source->getName(),
            'domain' => $source->getDomain(),
            'provider' => $source->getProvider(),
            'providerType' => $source->getProviderType()->value,
            'capabilities' => $source->getCapabilities(),
            'enabled' => $source->isEnabled(),
            'countryRestriction' => $source->getCountry()?->getName(),
        ], $sources);
    }

    private function shouldShowDiagnostics(): bool
    {
        return $this->getParameter('kernel.environment') !== 'prod' && (bool) $this->getParameter('kernel.debug');
    }
}
