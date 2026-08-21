<?php

namespace App\Modules\Hotel\Controller;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\District;
use App\Modules\Destination\Repository\CityRepository;
use App\Modules\Destination\Repository\DistrictRepository;
use App\Modules\Hotel\Form\HotelSearchType;
use App\Modules\Hotel\Service\HotelCandidatePayloadSigner;
use App\Modules\Hotel\Service\HotelImportService;
use App\Modules\Hotel\Service\HotelSearchService;
use App\Modules\Hotel\ValueObject\HotelSearchRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/catalog/hotels/search')]
class HotelSearchController extends AbstractController
{
    #[Route('', name: 'hotel_search', methods: ['GET'])]
    public function search(
        Request $request,
        HotelSearchService $hotelSearchService,
        CityRepository $cityRepository,
        DistrictRepository $districtRepository,
        HotelCandidatePayloadSigner $payloadSigner,
    ): Response {
        $form = $this->createForm(HotelSearchType::class);
        $form->handleRequest($request);
        $summary = null;
        $city = null;
        $district = null;

        if ($form->isSubmitted()) {
            $city = $this->city($cityRepository, $form->get('cityId')->getData());
            $district = $this->district($districtRepository, $form->get('districtId')->getData());
            if (!$city instanceof City) {
                $form->get('cityId')->addError(new FormError('hotel.lookup.city_required'));
            }
            if ($district instanceof District && $district->getCity() !== $city) {
                $form->get('districtId')->addError(new FormError('hotel.validation.district_city_mismatch'));
            }
            $query = trim((string) $form->get('query')->getData());
            if ($query === '') {
                $form->get('query')->addError(new FormError('hotel.search.query_required'));
            }

            if ($form->isValid() && $city instanceof City) {
                $summary = $hotelSearchService->search(new HotelSearchRequest($query, $city, $district));
            }
        }

        return $this->render('@Hotel/search/index.html.twig', [
            'form' => $form->createView(),
            'summary' => $summary,
            'city' => $city,
            'district' => $district,
            'payloadSigner' => $payloadSigner,
        ]);
    }

    #[Route('/import', name: 'hotel_search_import', methods: ['POST'])]
    public function import(
        Request $request,
        HotelCandidatePayloadSigner $payloadSigner,
        HotelImportService $hotelImportService,
        CityRepository $cityRepository,
        DistrictRepository $districtRepository,
    ): Response {
        if (!$this->isCsrfTokenValid('hotel_search_import', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'hotel.search.flash.invalid_import');

            return $this->redirectToRoute('hotel_search');
        }

        $candidate = $payloadSigner->verify((string) $request->request->get('candidate'), (string) $request->request->get('signature'));
        $city = $this->city($cityRepository, $request->request->get('city'));
        $district = $this->district($districtRepository, $request->request->get('district'));

        if ($candidate === null || !$city instanceof City || ($district instanceof District && $district->getCity() !== $city)) {
            $this->addFlash('danger', 'hotel.search.flash.invalid_import');

            return $this->redirectToRoute('hotel_search');
        }

        $result = $hotelImportService->import($candidate, $city, $district);
        $this->addFlash($result['created'] ? 'success' : 'info', $result['created'] ? 'hotel.search.flash.imported' : 'hotel.search.flash.matched');

        return $this->redirectToRoute('hotel_show', ['id' => $result['hotel']->getId()]);
    }

    private function city(CityRepository $cityRepository, mixed $id): ?City
    {
        $id = (int) $id;
        $city = $id > 0 ? $cityRepository->find($id) : null;

        return $city instanceof City ? $city : null;
    }

    private function district(DistrictRepository $districtRepository, mixed $id): ?District
    {
        $id = (int) $id;
        $district = $id > 0 ? $districtRepository->find($id) : null;

        return $district instanceof District ? $district : null;
    }
}
