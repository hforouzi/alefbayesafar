<?php

namespace App\Modules\Destination\Controller;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Repository\AirportRepository;
use App\Modules\Destination\Repository\CityRepository;
use App\Modules\Destination\Repository\CountryRepository;
use App\Modules\Destination\Repository\DistrictRepository;
use App\Modules\Destination\Repository\DestinationImportRunRepository;
use App\Modules\Destination\Repository\StateRepository;
use App\Modules\Destination\Service\AdminListRequest;
use App\Modules\Destination\Service\DestinationImportService;
use App\Modules\Destination\ValueObject\DestinationEntityType;
use App\Modules\Destination\ValueObject\DestinationImportRequest;
use App\Modules\Destination\ValueObject\DestinationImportSummary;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/catalog/import')]
class DestinationImportController extends AbstractController
{
    #[Route('/', name: 'destination_import_index', methods: ['GET', 'POST'])]
    public function index(
        CountryRepository $countryRepository,
        StateRepository $stateRepository,
        CityRepository $cityRepository,
        DistrictRepository $districtRepository,
        AirportRepository $airportRepository,
        DestinationImportRunRepository $importRunRepository,
        AdminListRequest $adminListRequest,
        #[Autowire('%destination.ourairports_cache_path%')]
        string $ourAirportsCachePath,
        Request $request,
    ): Response {
        $filters = $adminListRequest->importRunFilters($request);
        $recentRuns = $importRunRepository->findForAdminPage($filters);

        return $this->render('@Destination/import/index.html.twig', [
            'summary' => null,
            'counts' => [
                'countries' => $countryRepository->count([]),
                'states' => $stateRepository->count([]),
                'cities' => $cityRepository->count([]),
                'districts' => $districtRepository->count([]),
                'airports' => $airportRepository->count([]),
            ],
            'recentRuns' => $recentRuns->items,
            'pagination' => $recentRuns,
            'airportSourceMetadata' => $this->sourceMetadata($ourAirportsCachePath),
            'airportGlobalRun' => $importRunRepository->findLatestForTargetProvider(DestinationEntityType::AIRPORT, 'ourairports', 'GLOBAL'),
        ]);
    }

    #[Route('/bootstrap/{target}', name: 'destination_import_bootstrap', methods: ['POST'])]
    public function bootstrap(string $target, Request $request, DestinationImportService $importService): Response
    {
        if (!$this->isCsrfTokenValid('destination_import_bootstrap_' . $target, (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('destination_import_index');
        }

        $map = [
            'countries' => [DestinationEntityType::COUNTRY, 'GLOBAL', null, ['geonames']],
            'states' => [DestinationEntityType::STATE, 'GLOBAL', null, ['geonames']],
            'cities' => [DestinationEntityType::CITY, 'GLOBAL', null, ['geonames']],
            'airports' => [DestinationEntityType::AIRPORT, 'GLOBAL', null, ['ourairports']],
        ];

        if (!isset($map[$target])) {
            $this->addFlash('danger', 'destination.import.flash.invalid_target');

            return $this->redirectToRoute('destination_import_index');
        }

        [$targetType, $countryName, $cityName, $providers] = $map[$target];
        $summary = $importService->import(new DestinationImportRequest(
            targetType: $targetType,
            countryName: $countryName,
            cityName: $cityName,
            providerCodes: $providers,
            refresh: true,
        ));

        $this->addImportFlash($summary);

        return $this->redirectToRoute('destination_import_index');
    }

    #[Route('/enrichment/airports', name: 'destination_import_enrichment_airports', methods: ['POST'])]
    public function refreshAirports(Request $request, CityRepository $cityRepository, DestinationImportService $importService): Response
    {
        if (!$this->isCsrfTokenValid('destination_import_enrichment_airports', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('destination_import_index');
        }

        $city = $cityRepository->find($request->request->getInt('airport_city_id'));
        if (!$city instanceof City || !$city->getCountry() instanceof Country) {
            $this->addFlash('danger', 'destination.import.flash.city_required');

            return $this->redirectToRoute('destination_import_index');
        }

        $summary = $importService->import(new DestinationImportRequest(
            targetType: DestinationEntityType::AIRPORT,
            countryName: $city->getCountry()->getIso2() ?? $city->getCountry()->getName(),
            cityName: $city->getName(),
            providerCodes: ['ourairports'],
            refresh: true,
        ));
        $this->addImportFlash($summary);

        return $this->redirectToRoute('destination_import_index');
    }

    #[Route('/enrichment/travel-areas', name: 'destination_import_enrichment_travel_areas', methods: ['POST'])]
    public function refreshTravelAreas(Request $request, CityRepository $cityRepository, DestinationImportService $importService): Response
    {
        if (!$this->isCsrfTokenValid('destination_import_enrichment_travel_areas', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('destination_import_index');
        }

        $city = $cityRepository->find($request->request->getInt('city_id'));
        if (!$city instanceof City || !$city->getCountry() instanceof Country) {
            $this->addFlash('danger', 'destination.import.flash.city_required');

            return $this->redirectToRoute('destination_import_index');
        }

        $summary = $importService->import(new DestinationImportRequest(
            targetType: DestinationEntityType::CITY,
            countryName: $city->getCountry()->getName(),
            cityName: $city->getName(),
            providerCodes: ['wikivoyage'],
            refresh: true,
        ));
        $this->addImportFlash($summary);

        return $this->redirectToRoute('destination_import_index');
    }

    private function addImportFlash(DestinationImportSummary $summary): void
    {
        if ($summary->failed > 0 || $summary->errors !== []) {
            $this->addFlash('warning', 'destination.import.flash.completed_with_errors');

            return;
        }

        $this->addFlash('success', 'destination.import.flash.completed');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sourceMetadata(string $cachePath): ?array
    {
        $metadataPath = $cachePath . '.metadata.json';
        if (!is_readable($metadataPath)) {
            return null;
        }

        $metadata = json_decode((string) file_get_contents($metadataPath), true);

        return \is_array($metadata) ? $metadata : null;
    }
}
