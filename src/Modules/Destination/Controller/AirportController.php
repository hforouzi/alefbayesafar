<?php

namespace App\Modules\Destination\Controller;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Form\AirportType;
use App\Modules\Destination\Repository\AirportRepository;
use App\Modules\Destination\Repository\CityRepository;
use App\Modules\Destination\Service\AdminFilterLabelResolver;
use App\Modules\Destination\Service\AdminListRequest;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/catalog/airports')]
class AirportController extends AbstractController
{
    #[Route('/', name: 'destination_airport_index', methods: ['GET'])]
    public function index(Request $request, AirportRepository $airportRepository, AdminListRequest $adminListRequest, AdminFilterLabelResolver $labelResolver): Response
    {
        $filters = $adminListRequest->airportFilters($request);
        $airports = $airportRepository->findForAdminPage($filters);

        return $this->render('@Destination/airport/index.html.twig', [
            'airports' => $airports->items,
            'pagination' => $airports,
            'filterLabels' => $labelResolver->labels($filters),
        ]);
    }

    #[Route('/new', name: 'destination_airport_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, CityRepository $cityRepository): Response
    {
        $airport = new Airport();
        $form = $this->createForm(AirportType::class, $airport);
        $form->handleRequest($request);
        $this->assignSelectedCity($form, $airport, $cityRepository);

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
    public function edit(Request $request, Airport $airport, EntityManagerInterface $entityManager, CityRepository $cityRepository): Response
    {
        $form = $this->createForm(AirportType::class, $airport);
        $form->handleRequest($request);
        $this->assignSelectedCity($form, $airport, $cityRepository);

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

    private function assignSelectedCity(\Symfony\Component\Form\FormInterface $form, Airport $airport, CityRepository $cityRepository): void
    {
        if (!$form->isSubmitted()) {
            return;
        }

        $cityId = $form->get('cityId')->getData();
        $city = $cityId !== null && $cityId !== '' ? $cityRepository->find((int) $cityId) : null;
        if ($city === null) {
            $form->get('cityId')->addError(new FormError('destination.lookup.city_required'));

            return;
        }

        $airport->setCity($city);
    }
}
