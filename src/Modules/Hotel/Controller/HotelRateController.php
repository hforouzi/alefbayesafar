<?php

namespace App\Modules\Hotel\Controller;

use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelRate;
use App\Modules\Hotel\Form\HotelRateType;
use App\Modules\Hotel\Repository\HotelRateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/catalog/hotels/{hotel}/rates', requirements: ['hotel' => '\d+'])]
class HotelRateController extends AbstractController
{
    #[Route('', name: 'hotel_rate_index', methods: ['GET'])]
    public function index(Hotel $hotel, HotelRateRepository $rateRepository): Response
    {
        return $this->render('@Hotel/rate/index.html.twig', [
            'hotel' => $hotel,
            'rates' => $rateRepository->findForHotel($hotel),
        ]);
    }

    #[Route('/new', name: 'hotel_rate_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Hotel $hotel, EntityManagerInterface $entityManager): Response
    {
        $today = new \DateTimeImmutable('today');
        $rate = (new HotelRate())
            ->setHotel($hotel)
            ->setValidFrom($today)
            ->setValidTo($today->modify('+30 days'));
        $form = $this->createForm(HotelRateType::class, $rate, ['hotel' => $hotel]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($rate);
            $entityManager->flush();

            $this->addFlash('success', 'hotel.rate.flash.created');

            return $this->redirectToRoute('hotel_rate_index', ['hotel' => $hotel->getId()]);
        }

        return $this->render('@Hotel/rate/new.html.twig', [
            'hotel' => $hotel,
            'rate' => $rate,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{rate}/edit', name: 'hotel_rate_edit', requirements: ['rate' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Hotel $hotel, HotelRate $rate, EntityManagerInterface $entityManager): Response
    {
        $this->assertBelongsToHotel($hotel, $rate);

        $form = $this->createForm(HotelRateType::class, $rate, ['hotel' => $hotel]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'hotel.rate.flash.updated');

            return $this->redirectToRoute('hotel_rate_index', ['hotel' => $hotel->getId()]);
        }

        return $this->render('@Hotel/rate/edit.html.twig', [
            'hotel' => $hotel,
            'rate' => $rate,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{rate}/toggle', name: 'hotel_rate_toggle', requirements: ['rate' => '\d+'], methods: ['POST'])]
    public function toggle(Request $request, Hotel $hotel, HotelRate $rate, EntityManagerInterface $entityManager): Response
    {
        $this->assertBelongsToHotel($hotel, $rate);

        if ($this->isCsrfTokenValid('toggle_hotel_rate_' . $rate->getId(), (string) $request->request->get('_token'))) {
            $rate->setActive(!$rate->isActive());
            $entityManager->flush();
        }

        return $this->redirectToRoute('hotel_rate_index', ['hotel' => $hotel->getId()]);
    }

    private function assertBelongsToHotel(Hotel $hotel, HotelRate $rate): void
    {
        if ($rate->getHotel()?->getId() !== $hotel->getId()) {
            throw $this->createNotFoundException();
        }
    }
}
