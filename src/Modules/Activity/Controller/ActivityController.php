<?php

namespace App\Modules\Activity\Controller;

use App\Modules\Activity\Entity\Activity;
use App\Modules\Activity\Form\ActivityType;
use App\Modules\Activity\Repository\ActivityRepository;
use App\Modules\Activity\Service\ActivityAdminListRequest;
use App\Modules\Destination\Entity\City;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/activity-commerce/activities')]
class ActivityController extends AbstractController
{
    #[Route('/', name: 'activity_index', methods: ['GET'])]
    public function index(Request $request, ActivityRepository $activityRepository, ActivityAdminListRequest $adminListRequest): Response
    {
        $filters = $adminListRequest->activityFilters($request);
        $activities = $activityRepository->findForAdminPage($filters);

        return $this->render('@Activity/activity/index.html.twig', [
            'activities' => $activities->items,
            'pagination' => $activities,
        ]);
    }

    #[Route('/new', name: 'activity_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $activity = new Activity();
        $form = $this->createForm(ActivityType::class, $activity);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->applyUnmappedFields($form, $activity, $entityManager);
            $this->validateActivity($form, $activity, $validator);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($activity);
            $entityManager->flush();

            $this->addFlash('success', 'activity.activity.flash.created');

            return $this->redirectToRoute('activity_index');
        }

        return $this->render('@Activity/activity/new.html.twig', [
            'activity' => $activity,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'activity_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Activity $activity, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $form = $this->createForm(ActivityType::class, $activity);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->applyUnmappedFields($form, $activity, $entityManager);
            $this->validateActivity($form, $activity, $validator);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'activity.activity.flash.updated');

            return $this->redirectToRoute('activity_index');
        }

        return $this->render('@Activity/activity/edit.html.twig', [
            'activity' => $activity,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/toggle', name: 'activity_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Request $request, Activity $activity, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle_activity_' . $activity->getId(), (string) $request->request->get('_token'))) {
            $activity->setActive(!$activity->isActive());
            $entityManager->flush();
        }

        return $this->redirectToRoute('activity_index');
    }

    private function applyUnmappedFields(FormInterface $form, Activity $activity, EntityManagerInterface $entityManager): void
    {
        $value = (string) $form->get('cityId')->getData();
        if ($value === '' || !ctype_digit($value)) {
            $form->get('cityId')->addError(new FormError('activity.activity.validation.city_required'));

            return;
        }

        $city = $entityManager->getRepository(City::class)->find((int) $value);
        if (!$city instanceof City) {
            $form->get('cityId')->addError(new FormError('activity.activity.validation.invalid_reference'));

            return;
        }

        $activity->setCity($city);
    }

    private function validateActivity(FormInterface $form, Activity $activity, ValidatorInterface $validator): void
    {
        foreach ($validator->validate($activity) as $violation) {
            $field = $violation->getPropertyPath() === 'city' ? 'cityId' : $violation->getPropertyPath();
            if ($form->has($field)) {
                $form->get($field)->addError(new FormError((string) $violation->getMessage()));

                continue;
            }

            $form->addError(new FormError((string) $violation->getMessage()));
        }
    }
}
