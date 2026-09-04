<?php

namespace App\Modules\Transfer\Controller;

use App\Modules\Transfer\Entity\TransferOffer;
use App\Modules\Transfer\Entity\TransferProduct;
use App\Modules\Transfer\Form\TransferOfferType;
use App\Modules\Transfer\Repository\TransferOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/transfer-commerce/products/{product}/offers')]
class TransferOfferController extends AbstractController
{
    #[Route('/', name: 'transfer_offer_index', methods: ['GET'])]
    public function index(#[MapEntity(id: 'product')] TransferProduct $product, TransferOfferRepository $offerRepository): Response
    {
        return $this->render('@Transfer/offer/index.html.twig', [
            'product' => $product,
            'offers' => $offerRepository->findForProduct($product),
        ]);
    }

    #[Route('/new', name: 'transfer_offer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, #[MapEntity(id: 'product')] TransferProduct $product, EntityManagerInterface $entityManager): Response
    {
        $offer = (new TransferOffer())->setTransferProduct($product);
        $form = $this->createForm(TransferOfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $product->addOffer($offer);
            $entityManager->persist($offer);
            $entityManager->flush();

            $this->addFlash('success', 'transfer.offer.flash.created');

            return $this->redirectToRoute('transfer_offer_index', ['product' => $product->getId()]);
        }

        return $this->render('@Transfer/offer/new.html.twig', [
            'product' => $product,
            'offer' => $offer,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{offer}/edit', name: 'transfer_offer_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(id: 'product')] TransferProduct $product, #[MapEntity(id: 'offer')] TransferOffer $offer, EntityManagerInterface $entityManager): Response
    {
        if ($offer->getTransferProduct() !== $product) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(TransferOfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'transfer.offer.flash.updated');

            return $this->redirectToRoute('transfer_offer_index', ['product' => $product->getId()]);
        }

        return $this->render('@Transfer/offer/edit.html.twig', [
            'product' => $product,
            'offer' => $offer,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{offer}/toggle', name: 'transfer_offer_toggle', methods: ['POST'])]
    public function toggle(Request $request, #[MapEntity(id: 'product')] TransferProduct $product, #[MapEntity(id: 'offer')] TransferOffer $offer, EntityManagerInterface $entityManager): Response
    {
        if ($offer->getTransferProduct() !== $product) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('toggle_transfer_offer_' . $offer->getId(), (string) $request->request->get('_token'))) {
            $offer->setActive(!$offer->isActive());
            $entityManager->flush();
        }

        return $this->redirectToRoute('transfer_offer_index', ['product' => $product->getId()]);
    }
}
