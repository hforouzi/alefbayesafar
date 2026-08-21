<?php

namespace App\Modules\Hotel\Controller;

use App\Modules\Destination\Repository\CityRepository;
use App\Modules\Destination\Repository\DistrictRepository;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Form\HotelType;
use App\Modules\Hotel\Repository\HotelRepository;
use App\Modules\Hotel\Service\HotelAdminFilterLabelResolver;
use App\Modules\Hotel\Service\HotelAdminListRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/catalog/hotels')]
class HotelController extends AbstractController
{
    #[Route('/', name: 'hotel_index', methods: ['GET'])]
    public function index(Request $request, HotelRepository $hotelRepository, HotelAdminListRequest $adminListRequest, HotelAdminFilterLabelResolver $labelResolver): Response
    {
        $filters = $adminListRequest->hotelFilters($request);
        $hotels = $hotelRepository->findForAdminPage($filters);

        return $this->render('@Hotel/hotel/index.html.twig', [
            'hotels' => $hotels->items,
            'pagination' => $hotels,
            'filterLabels' => $labelResolver->labels($filters),
        ]);
    }

    #[Route('/new', name: 'hotel_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, CityRepository $cityRepository, DistrictRepository $districtRepository): Response
    {
        $hotel = new Hotel();
        $form = $this->createForm(HotelType::class, $hotel);
        $form->handleRequest($request);
        $this->validateSelectedLocation($form, $cityRepository, $districtRepository);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($hotel);
            $entityManager->flush();

            $this->addFlash('success', 'hotel.hotel.flash.created');

            return $this->redirectToRoute('hotel_show', ['id' => $hotel->getId()]);
        }

        return $this->render('@Hotel/hotel/new.html.twig', [
            'hotel' => $hotel,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'hotel_show', methods: ['GET'])]
    public function show(Hotel $hotel, HotelRepository $hotelRepository): Response
    {
        $loadedHotel = $hotel->getId() !== null ? $hotelRepository->findWithDetails($hotel->getId()) : null;

        return $this->render('@Hotel/hotel/show.html.twig', [
            'hotel' => $loadedHotel ?? $hotel,
        ]);
    }

    #[Route('/{id}/edit', name: 'hotel_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Hotel $hotel, EntityManagerInterface $entityManager, CityRepository $cityRepository, DistrictRepository $districtRepository): Response
    {
        $form = $this->createForm(HotelType::class, $hotel);
        $form->handleRequest($request);
        $this->validateSelectedLocation($form, $cityRepository, $districtRepository);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'hotel.hotel.flash.updated');

            return $this->redirectToRoute('hotel_show', ['id' => $hotel->getId()]);
        }

        return $this->render('@Hotel/hotel/edit.html.twig', [
            'hotel' => $hotel,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'hotel_delete', methods: ['POST'])]
    public function delete(Request $request, Hotel $hotel, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_hotel_' . $hotel->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('hotel_index');
        }

        $hotel->setActive(false);
        $entityManager->flush();
        $this->addFlash('warning', 'hotel.hotel.flash.deactivated');

        return $this->redirectToRoute('hotel_index');
    }

    private function validateSelectedLocation(FormInterface $form, CityRepository $cityRepository, DistrictRepository $districtRepository): void
    {
        if (!$form->isSubmitted()) {
            return;
        }

        $cityId = $form->get('cityId')->getData();
        $districtId = $form->get('districtId')->getData();
        $city = $cityId !== null && $cityId !== '' ? $cityRepository->find((int) $cityId) : null;
        $district = $districtId !== null && $districtId !== '' ? $districtRepository->find((int) $districtId) : null;

        if ($city === null) {
            $form->get('cityId')->addError(new FormError('hotel.lookup.city_required'));
        }

        if ($district !== null && $district->getCity() !== $city) {
            $form->get('districtId')->addError(new FormError('hotel.validation.district_city_mismatch'));
        }
    }
}
