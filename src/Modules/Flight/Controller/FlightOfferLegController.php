<?php

namespace App\Modules\Flight\Controller;

use App\Modules\Destination\Repository\AirportRepository;
use App\Modules\Flight\Entity\Airline;
use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Entity\FlightOfferLeg;
use App\Modules\Flight\Enum\FlightDirection;
use App\Modules\Flight\Enum\FlightPriceSourceType;
use App\Modules\Flight\Form\FlightOfferLegType;
use App\Modules\Flight\Repository\AirlineRepository;
use App\Modules\Flight\Repository\FlightOfferLegRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/flight-commerce/own-deals/{offer}/legs', requirements: ['offer' => '\d+'])]
class FlightOfferLegController extends AbstractController
{
    #[Route('', name: 'flight_offer_leg_index', methods: ['GET'])]
    public function index(FlightOffer $offer, FlightOfferLegRepository $legRepository): Response
    {
        $this->assertOwnDeal($offer);

        return $this->render('@Flight/flight_offer_leg/index.html.twig', [
            'offer' => $offer,
            'legs' => $legRepository->findForOffer($offer),
        ]);
    }

    #[Route('/new', name: 'flight_offer_leg_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        FlightOffer $offer,
        EntityManagerInterface $entityManager,
        AirlineRepository $airlineRepository,
        AirportRepository $airportRepository,
    ): Response {
        $this->assertOwnDeal($offer);

        $leg = (new FlightOfferLeg())
            ->setFlightOffer($offer)
            ->setDirection(FlightDirection::OUTBOUND)
            ->setSegmentIndex(\count($offer->getOrderedLegs(FlightDirection::OUTBOUND)))
            ->setDepartureAt(new \DateTimeImmutable('+1 day'))
            ->setArrivalAt(new \DateTimeImmutable('+1 day +4 hours'));

        $form = $this->createForm(FlightOfferLegType::class, $leg);
        $form->handleRequest($request);
        $this->assignRelations($form, $leg, $airlineRepository, $airportRepository);
        $this->validateLegAgainstOffer($form, $offer, $leg);

        if ($form->isSubmitted() && $form->isValid()) {
            $offer->addLeg($leg);
            $entityManager->persist($leg);
            $entityManager->flush();

            $this->addFlash('success', 'flight.leg.flash.created');

            return $this->redirectToRoute('flight_offer_leg_index', ['offer' => $offer->getId()]);
        }

        return $this->render('@Flight/flight_offer_leg/new.html.twig', [
            'offer' => $offer,
            'leg' => $leg,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{leg}/edit', name: 'flight_offer_leg_edit', requirements: ['leg' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        FlightOffer $offer,
        FlightOfferLeg $leg,
        EntityManagerInterface $entityManager,
        AirlineRepository $airlineRepository,
        AirportRepository $airportRepository,
    ): Response {
        $this->assertOwnDeal($offer);
        $this->assertBelongsToOffer($offer, $leg);

        $form = $this->createForm(FlightOfferLegType::class, $leg);
        $form->handleRequest($request);
        $this->assignRelations($form, $leg, $airlineRepository, $airportRepository);
        $this->validateLegAgainstOffer($form, $offer, $leg);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'flight.leg.flash.updated');

            return $this->redirectToRoute('flight_offer_leg_index', ['offer' => $offer->getId()]);
        }

        return $this->render('@Flight/flight_offer_leg/edit.html.twig', [
            'offer' => $offer,
            'leg' => $leg,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{leg}/delete', name: 'flight_offer_leg_delete', requirements: ['leg' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, FlightOffer $offer, FlightOfferLeg $leg, EntityManagerInterface $entityManager): Response
    {
        $this->assertOwnDeal($offer);
        $this->assertBelongsToOffer($offer, $leg);

        if ($this->isCsrfTokenValid('delete_flight_offer_leg_' . $leg->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($leg);
            $entityManager->flush();
            $this->addFlash('success', 'flight.leg.flash.deleted');
        }

        return $this->redirectToRoute('flight_offer_leg_index', ['offer' => $offer->getId()]);
    }

    private function assignRelations(FormInterface $form, FlightOfferLeg $leg, AirlineRepository $airlineRepository, AirportRepository $airportRepository): void
    {
        if (!$form->isSubmitted()) {
            return;
        }

        $airlineId = $form->get('airlineId')->getData();
        $airline = $airlineId !== null && $airlineId !== '' ? $airlineRepository->find((int) $airlineId) : null;
        if ($airlineId !== null && $airlineId !== '' && !$airline instanceof Airline) {
            $form->get('airlineId')->addError(new FormError('flight.leg.validation.airline_required'));
        }
        $leg->setAirline($airline);

        $originId = $form->get('originAirportId')->getData();
        $origin = $originId !== null && $originId !== '' ? $airportRepository->find((int) $originId) : null;
        if ($origin === null) {
            $form->get('originAirportId')->addError(new FormError('flight.leg.validation.origin_required'));
        }
        $leg->setOriginAirport($origin);

        $destinationId = $form->get('destinationAirportId')->getData();
        $destination = $destinationId !== null && $destinationId !== '' ? $airportRepository->find((int) $destinationId) : null;
        if ($destination === null) {
            $form->get('destinationAirportId')->addError(new FormError('flight.leg.validation.destination_required'));
        }
        $leg->setDestinationAirport($destination);
    }

    private function validateLegAgainstOffer(FormInterface $form, FlightOffer $offer, FlightOfferLeg $leg): void
    {
        if (!$form->isSubmitted()) {
            return;
        }

        if ($offer->getTripType()->value === 'one_way' && $leg->getDirection() === FlightDirection::INBOUND) {
            $form->get('direction')->addError(new FormError('flight.offer.validation.one_way_inbound_forbidden'));
        }
    }

    private function assertOwnDeal(FlightOffer $offer): void
    {
        if ($offer->getSourceType() !== FlightPriceSourceType::OWN) {
            throw $this->createNotFoundException();
        }
    }

    private function assertBelongsToOffer(FlightOffer $offer, FlightOfferLeg $leg): void
    {
        if ($leg->getFlightOffer()?->getId() !== $offer->getId()) {
            throw $this->createNotFoundException();
        }
    }
}
