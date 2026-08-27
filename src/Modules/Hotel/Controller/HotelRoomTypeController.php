<?php

namespace App\Modules\Hotel\Controller;

use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelRoomType;
use App\Modules\Hotel\Form\HotelRoomTypeType;
use App\Modules\Hotel\Repository\HotelRoomTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/catalog/hotels/{hotel}/room-types', requirements: ['hotel' => '\d+'])]
class HotelRoomTypeController extends AbstractController
{
    #[Route('', name: 'hotel_room_type_index', methods: ['GET'])]
    public function index(Hotel $hotel, HotelRoomTypeRepository $roomTypeRepository): Response
    {
        return $this->render('@Hotel/room_type/index.html.twig', [
            'hotel' => $hotel,
            'roomTypes' => $roomTypeRepository->findForHotel($hotel),
        ]);
    }

    #[Route('/new', name: 'hotel_room_type_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Hotel $hotel, EntityManagerInterface $entityManager): Response
    {
        $roomType = (new HotelRoomType())->setHotel($hotel);
        $form = $this->createForm(HotelRoomTypeType::class, $roomType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($roomType);
            $entityManager->flush();

            $this->addFlash('success', 'hotel.room_type.flash.created');

            return $this->redirectToRoute('hotel_room_type_index', ['hotel' => $hotel->getId()]);
        }

        return $this->render('@Hotel/room_type/new.html.twig', [
            'hotel' => $hotel,
            'roomType' => $roomType,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{roomType}/edit', name: 'hotel_room_type_edit', requirements: ['roomType' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Hotel $hotel, HotelRoomType $roomType, EntityManagerInterface $entityManager): Response
    {
        $this->assertBelongsToHotel($hotel, $roomType);

        $form = $this->createForm(HotelRoomTypeType::class, $roomType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'hotel.room_type.flash.updated');

            return $this->redirectToRoute('hotel_room_type_index', ['hotel' => $hotel->getId()]);
        }

        return $this->render('@Hotel/room_type/edit.html.twig', [
            'hotel' => $hotel,
            'roomType' => $roomType,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{roomType}/toggle', name: 'hotel_room_type_toggle', requirements: ['roomType' => '\d+'], methods: ['POST'])]
    public function toggle(Request $request, Hotel $hotel, HotelRoomType $roomType, EntityManagerInterface $entityManager): Response
    {
        $this->assertBelongsToHotel($hotel, $roomType);

        if ($this->isCsrfTokenValid('toggle_hotel_room_type_' . $roomType->getId(), (string) $request->request->get('_token'))) {
            $roomType->setActive(!$roomType->isActive());
            $entityManager->flush();
        }

        return $this->redirectToRoute('hotel_room_type_index', ['hotel' => $hotel->getId()]);
    }

    private function assertBelongsToHotel(Hotel $hotel, HotelRoomType $roomType): void
    {
        if ($roomType->getHotel()?->getId() !== $hotel->getId()) {
            throw $this->createNotFoundException();
        }
    }
}
