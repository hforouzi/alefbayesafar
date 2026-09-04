<?php

namespace App\Modules\Activity\Controller;

use App\Modules\Activity\Entity\Activity;
use App\Modules\Activity\Entity\ActivityImage;
use App\Modules\Activity\Form\ActivityImageType;
use App\Modules\Activity\Service\ActivityImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/activity-commerce/activities/{activity}/images')]
class ActivityImageController extends AbstractController
{
    #[Route('/new', name: 'activity_image_new', methods: ['GET', 'POST'])]
    public function new(Request $request, #[MapEntity(id: 'activity')] Activity $activity, EntityManagerInterface $entityManager, ActivityImageStorage $imageStorage): Response
    {
        $image = (new ActivityImage())
            ->setActivity($activity)
            ->setPosition($activity->getImages()->count());
        $form = $this->createForm(ActivityImageType::class, $image);
        $form->handleRequest($request);
        $storedUpload = false;

        if ($form->isSubmitted() && $form->isValid()) {
            $storedUpload = $this->applyUploadedFile($form, $image, $activity, $imageStorage);
        }

        if ($form->isSubmitted() && $form->isValid() && $image->getPath() === '') {
            $form->get('path')->addError(new FormError('activity.image.validation.path_or_file_required'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $activity->addImage($image);
            if ($image->isPrimary()) {
                $activity->markOnlyImagePrimary($image);
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

            $this->addFlash('success', 'activity.image.flash.created');

            return $this->redirectToRoute('activity_edit', ['id' => $activity->getId()]);
        }

        return $this->render('@Activity/image/new.html.twig', [
            'activity' => $activity,
            'image' => $image,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{image}/edit', name: 'activity_image_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(id: 'activity')] Activity $activity, #[MapEntity(id: 'image')] ActivityImage $image, EntityManagerInterface $entityManager, ActivityImageStorage $imageStorage): Response
    {
        if ($image->getActivity() !== $activity) {
            throw $this->createNotFoundException();
        }

        $previousPath = $image->getPath();
        $form = $this->createForm(ActivityImageType::class, $image);
        $form->handleRequest($request);
        $previousImage = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $previousImage = $this->applyUploadedFile($form, $image, $activity, $imageStorage, $previousPath);
        }

        if ($form->isSubmitted() && $form->isValid() && $image->getPath() === '') {
            $form->get('path')->addError(new FormError('activity.image.validation.path_or_file_required'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ($image->isPrimary()) {
                $activity->markOnlyImagePrimary($image);
            }
            try {
                $entityManager->flush();
            } catch (\Throwable $throwable) {
                if ($previousImage instanceof ActivityImage) {
                    $imageStorage->removeIfOwnedLocalFile($image);
                    $image->setPath($previousPath);
                }

                throw $throwable;
            }

            if ($previousImage instanceof ActivityImage) {
                $imageStorage->removeIfOwnedLocalFile($previousImage);
            }

            $this->addFlash('success', 'activity.image.flash.updated');

            return $this->redirectToRoute('activity_edit', ['id' => $activity->getId()]);
        }

        return $this->render('@Activity/image/edit.html.twig', [
            'activity' => $activity,
            'image' => $image,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{image}/delete', name: 'activity_image_delete', methods: ['POST'])]
    public function delete(Request $request, #[MapEntity(id: 'activity')] Activity $activity, #[MapEntity(id: 'image')] ActivityImage $image, EntityManagerInterface $entityManager, ActivityImageStorage $imageStorage): Response
    {
        if ($image->getActivity() !== $activity) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('delete_activity_image_' . $image->getId(), (string) $request->request->get('_token'))) {
            $imageStorage->removeIfOwnedLocalFile($image);
            $entityManager->remove($image);
            $entityManager->flush();
            $this->addFlash('success', 'activity.image.flash.deleted');
        }

        return $this->redirectToRoute('activity_edit', ['id' => $activity->getId()]);
    }

    private function applyUploadedFile(FormInterface $form, ActivityImage $image, Activity $activity, ActivityImageStorage $imageStorage, ?string $previousPath = null): bool|ActivityImage
    {
        $uploadedFile = $form->get('file')->getData();
        if (!$uploadedFile instanceof UploadedFile) {
            return false;
        }

        $previousImage = null;
        if ($previousPath !== null && $previousPath !== '') {
            $previousImage = (new ActivityImage())->setActivity($activity)->setPath($previousPath);
        }

        $image->setPath($imageStorage->store($activity, $uploadedFile));

        return $previousImage ?? true;
    }
}
