<?php

namespace App\Modules\Hotel\Controller;

use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelImage;
use App\Modules\Hotel\Form\HotelImageType;
use App\Modules\Hotel\Service\HotelImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/catalog/hotels/{hotel}/images')]
class HotelImageController extends AbstractController
{
    #[Route('/new', name: 'hotel_image_new', methods: ['GET', 'POST'])]
    public function new(Request $request, #[MapEntity(id: 'hotel')] Hotel $hotel, EntityManagerInterface $entityManager, HotelImageStorage $imageStorage): Response
    {
        $image = new HotelImage();
        $image->setHotel($hotel);
        $image->setPosition($hotel->getImages()->count());
        $form = $this->createForm(HotelImageType::class, $image);
        $form->handleRequest($request);
        $storedUpload = false;

        if ($form->isSubmitted() && $form->isValid()) {
            $storedUpload = $this->applyUploadedFile($form, $image, $hotel, $imageStorage);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ($image->getPath() === '') {
                $form->get('path')->addError(new FormError('hotel.image.validation.path_or_file_required'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $hotel->addImage($image);
            if ($image->isPrimary()) {
                $hotel->markOnlyImagePrimary($image);
            }
            $entityManager->persist($image);
            try {
                $entityManager->flush();
            } catch (\Throwable $throwable) {
                if ($storedUpload) {
                    $imageStorage->removeIfOwnedLocalFile($image);
                }

                throw $throwable;
            }

            $this->addFlash('success', 'hotel.image.flash.created');

            return $this->redirectToRoute('hotel_show', ['id' => $hotel->getId()]);
        }

        return $this->render('@Hotel/image/new.html.twig', [
            'hotel' => $hotel,
            'image' => $image,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{image}/edit', name: 'hotel_image_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(id: 'hotel')] Hotel $hotel, #[MapEntity(id: 'image')] HotelImage $image, EntityManagerInterface $entityManager, HotelImageStorage $imageStorage): Response
    {
        if ($image->getHotel() !== $hotel) {
            throw $this->createNotFoundException();
        }

        $previousPath = $image->getPath();
        $form = $this->createForm(HotelImageType::class, $image);
        $form->handleRequest($request);
        $previousImage = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $previousImage = $this->applyUploadedFile($form, $image, $hotel, $imageStorage, $previousPath);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ($image->getPath() === '') {
                $form->get('path')->addError(new FormError('hotel.image.validation.path_or_file_required'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ($image->isPrimary()) {
                $hotel->markOnlyImagePrimary($image);
            }
            try {
                $entityManager->flush();
            } catch (\Throwable $throwable) {
                if ($previousImage instanceof HotelImage) {
                    $imageStorage->removeIfOwnedLocalFile($image);
                    $image->setPath($previousPath);
                }

                throw $throwable;
            }

            if ($previousImage instanceof HotelImage) {
                $imageStorage->removeIfOwnedLocalFile($previousImage);
            }

            $this->addFlash('success', 'hotel.image.flash.updated');

            return $this->redirectToRoute('hotel_show', ['id' => $hotel->getId()]);
        }

        return $this->render('@Hotel/image/edit.html.twig', [
            'hotel' => $hotel,
            'image' => $image,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{image}/delete', name: 'hotel_image_delete', methods: ['POST'])]
    public function delete(Request $request, #[MapEntity(id: 'hotel')] Hotel $hotel, #[MapEntity(id: 'image')] HotelImage $image, EntityManagerInterface $entityManager, HotelImageStorage $imageStorage): Response
    {
        if ($image->getHotel() !== $hotel) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('delete_hotel_image_' . $image->getId(), (string) $request->request->get('_token'))) {
            $imageStorage->removeIfOwnedLocalFile($image);
            $entityManager->remove($image);
            $entityManager->flush();
            $this->addFlash('success', 'hotel.image.flash.deleted');
        }

        return $this->redirectToRoute('hotel_show', ['id' => $hotel->getId()]);
    }

    private function applyUploadedFile(FormInterface $form, HotelImage $image, Hotel $hotel, HotelImageStorage $imageStorage, ?string $previousPath = null): bool|HotelImage
    {
        $uploadedFile = $form->get('file')->getData();
        if (!$uploadedFile instanceof UploadedFile) {
            return false;
        }

        $previousImage = null;
        if ($previousPath !== null && $previousPath !== '') {
            $previousImage = (new HotelImage())->setHotel($hotel)->setPath($previousPath);
        }

        $image->setPath($imageStorage->store($hotel, $uploadedFile));

        return $previousImage ?? true;
    }
}
