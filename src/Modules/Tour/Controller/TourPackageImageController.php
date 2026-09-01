<?php

namespace App\Modules\Tour\Controller;

use App\Modules\Tour\Entity\TourPackage;
use App\Modules\Tour\Entity\TourPackageImage;
use App\Modules\Tour\Form\TourPackageImageType;
use App\Modules\Tour\Service\TourPackageImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/tour-commerce/packages/{package}/images')]
class TourPackageImageController extends AbstractController
{
    #[Route('/new', name: 'tour_package_image_new', methods: ['GET', 'POST'])]
    public function new(Request $request, #[MapEntity(id: 'package')] TourPackage $package, EntityManagerInterface $entityManager, TourPackageImageStorage $imageStorage): Response
    {
        $image = (new TourPackageImage())
            ->setTourPackage($package)
            ->setPosition($package->getImages()->count());
        $form = $this->createForm(TourPackageImageType::class, $image);
        $form->handleRequest($request);
        $storedUpload = false;

        if ($form->isSubmitted() && $form->isValid()) {
            $storedUpload = $this->applyUploadedFile($form, $image, $package, $imageStorage);
        }

        if ($form->isSubmitted() && $form->isValid() && $image->getPath() === '') {
            $form->get('path')->addError(new FormError('tour.image.validation.path_or_file_required'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $package->addImage($image);
            if ($image->isPrimary()) {
                $package->markOnlyImagePrimary($image);
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

            $this->addFlash('success', 'tour.image.flash.created');

            return $this->redirectToRoute('tour_package_edit', ['id' => $package->getId()]);
        }

        return $this->render('@Tour/image/new.html.twig', [
            'package' => $package,
            'image' => $image,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{image}/edit', name: 'tour_package_image_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(id: 'package')] TourPackage $package, #[MapEntity(id: 'image')] TourPackageImage $image, EntityManagerInterface $entityManager, TourPackageImageStorage $imageStorage): Response
    {
        if ($image->getTourPackage() !== $package) {
            throw $this->createNotFoundException();
        }

        $previousPath = $image->getPath();
        $form = $this->createForm(TourPackageImageType::class, $image);
        $form->handleRequest($request);
        $previousImage = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $previousImage = $this->applyUploadedFile($form, $image, $package, $imageStorage, $previousPath);
        }

        if ($form->isSubmitted() && $form->isValid() && $image->getPath() === '') {
            $form->get('path')->addError(new FormError('tour.image.validation.path_or_file_required'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ($image->isPrimary()) {
                $package->markOnlyImagePrimary($image);
            }
            try {
                $entityManager->flush();
            } catch (\Throwable $throwable) {
                if ($previousImage instanceof TourPackageImage) {
                    $imageStorage->removeIfOwnedLocalFile($image);
                    $image->setPath($previousPath);
                }

                throw $throwable;
            }

            if ($previousImage instanceof TourPackageImage) {
                $imageStorage->removeIfOwnedLocalFile($previousImage);
            }

            $this->addFlash('success', 'tour.image.flash.updated');

            return $this->redirectToRoute('tour_package_edit', ['id' => $package->getId()]);
        }

        return $this->render('@Tour/image/edit.html.twig', [
            'package' => $package,
            'image' => $image,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{image}/delete', name: 'tour_package_image_delete', methods: ['POST'])]
    public function delete(Request $request, #[MapEntity(id: 'package')] TourPackage $package, #[MapEntity(id: 'image')] TourPackageImage $image, EntityManagerInterface $entityManager, TourPackageImageStorage $imageStorage): Response
    {
        if ($image->getTourPackage() !== $package) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('delete_tour_package_image_' . $image->getId(), (string) $request->request->get('_token'))) {
            $imageStorage->removeIfOwnedLocalFile($image);
            $entityManager->remove($image);
            $entityManager->flush();
            $this->addFlash('success', 'tour.image.flash.deleted');
        }

        return $this->redirectToRoute('tour_package_edit', ['id' => $package->getId()]);
    }

    private function applyUploadedFile(FormInterface $form, TourPackageImage $image, TourPackage $package, TourPackageImageStorage $imageStorage, ?string $previousPath = null): bool|TourPackageImage
    {
        $uploadedFile = $form->get('file')->getData();
        if (!$uploadedFile instanceof UploadedFile) {
            return false;
        }

        $previousImage = null;
        if ($previousPath !== null && $previousPath !== '') {
            $previousImage = (new TourPackageImage())->setTourPackage($package)->setPath($previousPath);
        }

        $image->setPath($imageStorage->store($package, $uploadedFile));

        return $previousImage ?? true;
    }
}
