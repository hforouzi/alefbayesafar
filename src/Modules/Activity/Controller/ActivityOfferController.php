<?php

namespace App\Modules\Activity\Controller;

use App\Modules\Activity\Entity\Activity;
use App\Modules\Activity\Entity\ActivityOffer;
use App\Modules\Activity\Enum\ActivityPricingMode;
use App\Modules\Activity\Form\ActivityOfferType;
use App\Modules\Activity\Repository\ActivityOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/activity-commerce/activities/{activity}/offers')]
class ActivityOfferController extends AbstractController
{
    #[Route('/', name: 'activity_offer_index', methods: ['GET'])]
    public function index(#[MapEntity(id: 'activity')] Activity $activity, ActivityOfferRepository $offerRepository): Response
    {
        return $this->render('@Activity/offer/index.html.twig', [
            'activity' => $activity,
            'offers' => $offerRepository->findForActivity($activity),
        ]);
    }

    #[Route('/new', name: 'activity_offer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, #[MapEntity(id: 'activity')] Activity $activity, EntityManagerInterface $entityManager): Response
    {
        $offer = (new ActivityOffer())->setActivity($activity);
        $form = $this->createForm(ActivityOfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->normalizeUnusedPrices($offer);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $activity->addOffer($offer);
            $entityManager->persist($offer);
            $entityManager->flush();

            $this->addFlash('success', 'activity.offer.flash.created');

            return $this->redirectToRoute('activity_offer_index', ['activity' => $activity->getId()]);
        }

        return $this->render('@Activity/offer/new.html.twig', [
            'activity' => $activity,
            'offer' => $offer,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{offer}/edit', name: 'activity_offer_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(id: 'activity')] Activity $activity, #[MapEntity(id: 'offer')] ActivityOffer $offer, EntityManagerInterface $entityManager): Response
    {
        if ($offer->getActivity() !== $activity) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(ActivityOfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->normalizeUnusedPrices($offer);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'activity.offer.flash.updated');

            return $this->redirectToRoute('activity_offer_index', ['activity' => $activity->getId()]);
        }

        return $this->render('@Activity/offer/edit.html.twig', [
            'activity' => $activity,
            'offer' => $offer,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{offer}/toggle', name: 'activity_offer_toggle', methods: ['POST'])]
    public function toggle(Request $request, #[MapEntity(id: 'activity')] Activity $activity, #[MapEntity(id: 'offer')] ActivityOffer $offer, EntityManagerInterface $entityManager): Response
    {
        if ($offer->getActivity() !== $activity) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('toggle_activity_offer_' . $offer->getId(), (string) $request->request->get('_token'))) {
            $offer->setActive(!$offer->isActive());
            $entityManager->flush();
        }

        return $this->redirectToRoute('activity_offer_index', ['activity' => $activity->getId()]);
    }

    private function normalizeUnusedPrices(ActivityOffer $offer): void
    {
        if ($offer->getPricingMode() === ActivityPricingMode::TOTAL_PARTY) {
            $offer
                ->setAdultPrice(null)
                ->setChildPrice(null)
                ->setInfantPrice(null);

            return;
        }

        $offer->setTotalPrice(null);
    }
}
