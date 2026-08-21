<?php

namespace App\Modules\Hotel\Controller;

use App\Modules\Hotel\Entity\HotelAmenity;
use App\Modules\Hotel\Form\HotelAmenityType;
use App\Modules\Hotel\Repository\HotelAmenityRepository;
use App\Modules\Hotel\Service\HotelAdminListRequest;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/catalog/hotel-amenities')]
class HotelAmenityController extends AbstractController
{
    #[Route('/', name: 'hotel_amenity_index', methods: ['GET'])]
    public function index(Request $request, HotelAmenityRepository $amenityRepository, HotelAdminListRequest $adminListRequest): Response
    {
        $filters = $adminListRequest->amenityFilters($request);
        $amenities = $amenityRepository->findForAdminPage($filters);

        return $this->render('@Hotel/amenity/index.html.twig', [
            'amenities' => $amenities->items,
            'pagination' => $amenities,
        ]);
    }

    #[Route('/new', name: 'hotel_amenity_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $amenity = new HotelAmenity();
        $form = $this->createForm(HotelAmenityType::class, $amenity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($amenity);
            $entityManager->flush();

            $this->addFlash('success', 'hotel.amenity.flash.created');

            return $this->redirectToRoute('hotel_amenity_index');
        }

        return $this->render('@Hotel/amenity/new.html.twig', [
            'amenity' => $amenity,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'hotel_amenity_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, HotelAmenity $amenity, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(HotelAmenityType::class, $amenity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'hotel.amenity.flash.updated');

            return $this->redirectToRoute('hotel_amenity_index');
        }

        return $this->render('@Hotel/amenity/edit.html.twig', [
            'amenity' => $amenity,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'hotel_amenity_delete', methods: ['POST'])]
    public function delete(Request $request, HotelAmenity $amenity, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_hotel_amenity_' . $amenity->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('hotel_amenity_index');
        }

        if ($amenity->getHotels()->count() > 0) {
            $amenity->setActive(false);
            $entityManager->flush();
            $this->addFlash('warning', 'hotel.amenity.flash.deactivated_in_use');

            return $this->redirectToRoute('hotel_amenity_index');
        }

        try {
            $entityManager->remove($amenity);
            $entityManager->flush();
            $this->addFlash('success', 'hotel.amenity.flash.deleted');
        } catch (ForeignKeyConstraintViolationException) {
            $amenity->setActive(false);
            $entityManager->flush();
            $this->addFlash('warning', 'hotel.amenity.flash.deactivated_in_use');
        }

        return $this->redirectToRoute('hotel_amenity_index');
    }
}
