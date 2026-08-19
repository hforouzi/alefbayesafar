<?php

namespace App\Modules\Destination\Controller;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Form\AirportType;
use App\Modules\Destination\Repository\AirportRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/catalog/airports')]
class AirportController extends AbstractController
{
    #[Route('/', name: 'destination_airport_index', methods: ['GET'])]
    public function index(AirportRepository $airportRepository): Response
    {
        return $this->render('@Destination/airport/index.html.twig', [
            'airports' => $airportRepository->findForAdminList(),
        ]);
    }

    #[Route('/new', name: 'destination_airport_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $airport = new Airport();
        $form = $this->createForm(AirportType::class, $airport);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($airport);
            $entityManager->flush();

            $this->addFlash('success', 'destination.airport.flash.created');

            return $this->redirectToRoute('destination_airport_index');
        }

        return $this->render('@Destination/airport/new.html.twig', [
            'airport' => $airport,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'destination_airport_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Airport $airport, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AirportType::class, $airport);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'destination.airport.flash.updated');

            return $this->redirectToRoute('destination_airport_index');
        }

        return $this->render('@Destination/airport/edit.html.twig', [
            'airport' => $airport,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'destination_airport_delete', methods: ['POST'])]
    public function delete(Request $request, Airport $airport, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_destination_airport_' . $airport->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('destination_airport_index');
        }

        try {
            $entityManager->remove($airport);
            $entityManager->flush();
            $this->addFlash('success', 'destination.airport.flash.deleted');
        } catch (ForeignKeyConstraintViolationException) {
            $airport->setActive(false);
            $entityManager->flush();
            $this->addFlash('warning', 'destination.airport.flash.deactivated_in_use');
        }

        return $this->redirectToRoute('destination_airport_index');
    }
}
