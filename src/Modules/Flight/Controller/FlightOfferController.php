<?php

namespace App\Modules\Flight\Controller;

use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Enum\FlightPriceSourceType;
use App\Modules\Flight\Form\FlightOfferType;
use App\Modules\Flight\Repository\FlightOfferRepository;
use App\Modules\Flight\Service\FlightAdminListRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/flight-commerce/own-deals')]
class FlightOfferController extends AbstractController
{
    #[Route('/', name: 'flight_offer_index', methods: ['GET'])]
    public function index(Request $request, FlightOfferRepository $offerRepository, FlightAdminListRequest $adminListRequest): Response
    {
        $filters = $adminListRequest->ownFlightDealFilters($request);
        $offers = $offerRepository->findOwnForAdminPage($filters);

        return $this->render('@Flight/flight_offer/index.html.twig', [
            'offers' => $offers->items,
            'pagination' => $offers,
        ]);
    }

    #[Route('/new', name: 'flight_offer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $offer = (new FlightOffer())
            ->setSourceType(FlightPriceSourceType::OWN)
            ->setSearchSource(null)
            ->setFetchedAt(null)
            ->setExpiresAt(null);

        $form = $this->createForm(FlightOfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->forceOwnDealInvariants($offer);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($offer);
            $entityManager->flush();

            $this->addFlash('success', 'flight.offer.flash.created');

            return $this->redirectToRoute('flight_offer_leg_index', ['offer' => $offer->getId()]);
        }

        return $this->render('@Flight/flight_offer/new.html.twig', [
            'offer' => $offer,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'flight_offer_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, FlightOffer $offer, EntityManagerInterface $entityManager): Response
    {
        $this->assertOwnDeal($offer);

        $form = $this->createForm(FlightOfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->forceOwnDealInvariants($offer);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'flight.offer.flash.updated');

            return $this->redirectToRoute('flight_offer_index');
        }

        return $this->render('@Flight/flight_offer/edit.html.twig', [
            'offer' => $offer,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/toggle', name: 'flight_offer_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Request $request, FlightOffer $offer, EntityManagerInterface $entityManager): Response
    {
        $this->assertOwnDeal($offer);

        if ($this->isCsrfTokenValid('toggle_flight_offer_' . $offer->getId(), (string) $request->request->get('_token'))) {
            $offer->setActive(!$offer->isActive());
            $entityManager->flush();
        }

        return $this->redirectToRoute('flight_offer_index');
    }

    private function forceOwnDealInvariants(FlightOffer $offer): void
    {
        $offer
            ->setSourceType(FlightPriceSourceType::OWN)
            ->setSearchSource(null)
            ->setFetchedAt(null)
            ->setExpiresAt(null)
            ->setProviderCode(null)
            ->setExternalOfferId(null);
    }

    private function assertOwnDeal(FlightOffer $offer): void
    {
        if ($offer->getSourceType() !== FlightPriceSourceType::OWN) {
            throw $this->createNotFoundException();
        }
    }
}
