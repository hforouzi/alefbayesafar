<?php

namespace App\Modules\Flight\Controller;

use App\Modules\Flight\Entity\Airline;
use App\Modules\Flight\Form\AirlineType;
use App\Modules\Flight\Repository\AirlineRepository;
use App\Modules\Flight\Service\FlightAdminListRequest;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/flight-commerce/airlines')]
class AirlineController extends AbstractController
{
    #[Route('/', name: 'flight_airline_index', methods: ['GET'])]
    public function index(Request $request, AirlineRepository $airlineRepository, FlightAdminListRequest $adminListRequest): Response
    {
        $filters = $adminListRequest->airlineFilters($request);
        $airlines = $airlineRepository->findForAdminPage($filters);

        return $this->render('@Flight/airline/index.html.twig', [
            'airlines' => $airlines->items,
            'pagination' => $airlines,
        ]);
    }

    #[Route('/new', name: 'flight_airline_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $airline = new Airline();
        $form = $this->createForm(AirlineType::class, $airline);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($airline);
            $entityManager->flush();

            $this->addFlash('success', 'flight.airline.flash.created');

            return $this->redirectToRoute('flight_airline_index');
        }

        return $this->render('@Flight/airline/new.html.twig', [
            'airline' => $airline,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'flight_airline_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Airline $airline, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AirlineType::class, $airline);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'flight.airline.flash.updated');

            return $this->redirectToRoute('flight_airline_index');
        }

        return $this->render('@Flight/airline/edit.html.twig', [
            'airline' => $airline,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'flight_airline_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Airline $airline, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_flight_airline_' . $airline->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('flight_airline_index');
        }

        try {
            $entityManager->remove($airline);
            $entityManager->flush();
            $this->addFlash('success', 'flight.airline.flash.deleted');
        } catch (ForeignKeyConstraintViolationException) {
            $airline->setActive(false);
            $entityManager->flush();
            $this->addFlash('warning', 'flight.airline.flash.deactivated_in_use');
        }

        return $this->redirectToRoute('flight_airline_index');
    }
}
