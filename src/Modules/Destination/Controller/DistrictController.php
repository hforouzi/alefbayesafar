<?php

namespace App\Modules\Destination\Controller;

use App\Modules\Destination\Entity\District;
use App\Modules\Destination\Form\DistrictType;
use App\Modules\Destination\Repository\CityRepository;
use App\Modules\Destination\Repository\DistrictRepository;
use App\Modules\Destination\Service\AdminFilterLabelResolver;
use App\Modules\Destination\Service\AdminListRequest;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/catalog/districts')]
class DistrictController extends AbstractController
{
    #[Route('/', name: 'destination_district_index', methods: ['GET'])]
    public function index(Request $request, DistrictRepository $districtRepository, AdminListRequest $adminListRequest, AdminFilterLabelResolver $labelResolver): Response
    {
        $filters = $adminListRequest->districtFilters($request);
        $districts = $districtRepository->findForAdminPage($filters);

        return $this->render('@Destination/district/index.html.twig', [
            'districts' => $districts->items,
            'pagination' => $districts,
            'filterLabels' => $labelResolver->labels($filters),
        ]);
    }

    #[Route('/new', name: 'destination_district_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, CityRepository $cityRepository): Response
    {
        $district = new District();
        $form = $this->createForm(DistrictType::class, $district);
        $form->handleRequest($request);
        $this->assignSelectedCity($form, $district, $cityRepository);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($district);
            $entityManager->flush();

            $this->addFlash('success', 'destination.district.flash.created');

            return $this->redirectToRoute('destination_district_index');
        }

        return $this->render('@Destination/district/new.html.twig', [
            'district' => $district,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'destination_district_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, District $district, EntityManagerInterface $entityManager, CityRepository $cityRepository): Response
    {
        $form = $this->createForm(DistrictType::class, $district);
        $form->handleRequest($request);
        $this->assignSelectedCity($form, $district, $cityRepository);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'destination.district.flash.updated');

            return $this->redirectToRoute('destination_district_index');
        }

        return $this->render('@Destination/district/edit.html.twig', [
            'district' => $district,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'destination_district_delete', methods: ['POST'])]
    public function delete(Request $request, District $district, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_destination_district_' . $district->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('destination_district_index');
        }

        try {
            $entityManager->remove($district);
            $entityManager->flush();
            $this->addFlash('success', 'destination.district.flash.deleted');
        } catch (ForeignKeyConstraintViolationException) {
            $district->setActive(false);
            $entityManager->flush();
            $this->addFlash('warning', 'destination.district.flash.deactivated_in_use');
        }

        return $this->redirectToRoute('destination_district_index');
    }

    private function assignSelectedCity(\Symfony\Component\Form\FormInterface $form, District $district, CityRepository $cityRepository): void
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

        $district->setCity($city);
    }
}
