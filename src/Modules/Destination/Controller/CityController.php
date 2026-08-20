<?php

namespace App\Modules\Destination\Controller;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Form\CityType;
use App\Modules\Destination\Repository\CityRepository;
use App\Modules\Destination\Service\AdminFilterLabelResolver;
use App\Modules\Destination\Service\AdminListRequest;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/catalog/cities')]
class CityController extends AbstractController
{
    #[Route('/', name: 'destination_city_index', methods: ['GET'])]
    public function index(Request $request, CityRepository $cityRepository, AdminListRequest $adminListRequest, AdminFilterLabelResolver $labelResolver): Response
    {
        $filters = $adminListRequest->cityFilters($request);
        $cities = $cityRepository->findForAdminPage($filters);

        return $this->render('@Destination/city/index.html.twig', [
            'cities' => $cities->items,
            'pagination' => $cities,
            'filterLabels' => $labelResolver->labels($filters),
        ]);
    }

    #[Route('/new', name: 'destination_city_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $city = new City();
        $form = $this->createForm(CityType::class, $city);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($city);
            $entityManager->flush();

            $this->addFlash('success', 'destination.city.flash.created');

            return $this->redirectToRoute('destination_city_index');
        }

        return $this->render('@Destination/city/new.html.twig', [
            'city' => $city,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'destination_city_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, City $city, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CityType::class, $city);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'destination.city.flash.updated');

            return $this->redirectToRoute('destination_city_index');
        }

        return $this->render('@Destination/city/edit.html.twig', [
            'city' => $city,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'destination_city_delete', methods: ['POST'])]
    public function delete(Request $request, City $city, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_destination_city_' . $city->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('destination_city_index');
        }

        if ($city->getDistricts()->count() > 0 || $city->getAirports()->count() > 0) {
            $city->setActive(false);
            $entityManager->flush();
            $this->addFlash('warning', 'destination.city.flash.deactivated_has_children');

            return $this->redirectToRoute('destination_city_index');
        }

        try {
            $entityManager->remove($city);
            $entityManager->flush();
            $this->addFlash('success', 'destination.city.flash.deleted');
        } catch (ForeignKeyConstraintViolationException) {
            $city->setActive(false);
            $entityManager->flush();
            $this->addFlash('warning', 'destination.city.flash.deactivated_has_children');
        }

        return $this->redirectToRoute('destination_city_index');
    }
}
