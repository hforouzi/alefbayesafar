<?php

namespace App\Modules\Destination\Controller;

use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Form\CountryType;
use App\Modules\Destination\Repository\CountryRepository;
use App\Modules\Destination\Service\AdminListRequest;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/catalog/countries')]
class CountryController extends AbstractController
{
    #[Route('/', name: 'destination_country_index', methods: ['GET'])]
    public function index(Request $request, CountryRepository $countryRepository, AdminListRequest $adminListRequest): Response
    {
        $filters = $adminListRequest->countryFilters($request);
        $countries = $countryRepository->findForAdminPage($filters);

        return $this->render('@Destination/country/index.html.twig', [
            'countries' => $countries->items,
            'pagination' => $countries,
        ]);
    }

    #[Route('/new', name: 'destination_country_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $country = new Country();
        $form = $this->createForm(CountryType::class, $country);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($country);
            $entityManager->flush();

            $this->addFlash('success', 'destination.country.flash.created');

            return $this->redirectToRoute('destination_country_index');
        }

        return $this->render('@Destination/country/new.html.twig', [
            'country' => $country,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'destination_country_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Country $country, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CountryType::class, $country);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'destination.country.flash.updated');

            return $this->redirectToRoute('destination_country_index');
        }

        return $this->render('@Destination/country/edit.html.twig', [
            'country' => $country,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'destination_country_delete', methods: ['POST'])]
    public function delete(Request $request, Country $country, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_destination_country_' . $country->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('destination_country_index');
        }

        if ($country->getCities()->count() > 0) {
            $country->setActive(false);
            $entityManager->flush();
            $this->addFlash('warning', 'destination.country.flash.deactivated_has_children');

            return $this->redirectToRoute('destination_country_index');
        }

        try {
            $entityManager->remove($country);
            $entityManager->flush();
            $this->addFlash('success', 'destination.country.flash.deleted');
        } catch (ForeignKeyConstraintViolationException) {
            $country->setActive(false);
            $entityManager->flush();
            $this->addFlash('warning', 'destination.country.flash.deactivated_has_children');
        }

        return $this->redirectToRoute('destination_country_index');
    }
}
