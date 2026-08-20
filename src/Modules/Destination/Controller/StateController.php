<?php

namespace App\Modules\Destination\Controller;

use App\Modules\Destination\Entity\State;
use App\Modules\Destination\Form\StateType;
use App\Modules\Destination\Repository\StateRepository;
use App\Modules\Destination\Service\AdminFilterLabelResolver;
use App\Modules\Destination\Service\AdminListRequest;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/catalog/states')]
class StateController extends AbstractController
{
    #[Route('/', name: 'destination_state_index', methods: ['GET'])]
    public function index(Request $request, StateRepository $stateRepository, AdminListRequest $adminListRequest, AdminFilterLabelResolver $labelResolver): Response
    {
        $filters = $adminListRequest->stateFilters($request);
        $states = $stateRepository->findForAdminPage($filters);

        return $this->render('@Destination/state/index.html.twig', [
            'states' => $states->items,
            'pagination' => $states,
            'filterLabels' => $labelResolver->labels($filters),
        ]);
    }

    #[Route('/new', name: 'destination_state_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $state = new State();
        $form = $this->createForm(StateType::class, $state);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($state);
            $entityManager->flush();

            $this->addFlash('success', 'destination.state.flash.created');

            return $this->redirectToRoute('destination_state_index');
        }

        return $this->render('@Destination/state/new.html.twig', [
            'state' => $state,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'destination_state_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, State $state, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(StateType::class, $state);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'destination.state.flash.updated');

            return $this->redirectToRoute('destination_state_index');
        }

        return $this->render('@Destination/state/edit.html.twig', [
            'state' => $state,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'destination_state_delete', methods: ['POST'])]
    public function delete(Request $request, State $state, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_destination_state_' . $state->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('destination_state_index');
        }

        if ($state->getCities()->count() > 0) {
            $state->setActive(false);
            $entityManager->flush();
            $this->addFlash('warning', 'destination.state.flash.deactivated_has_children');

            return $this->redirectToRoute('destination_state_index');
        }

        try {
            $entityManager->remove($state);
            $entityManager->flush();
            $this->addFlash('success', 'destination.state.flash.deleted');
        } catch (ForeignKeyConstraintViolationException) {
            $state->setActive(false);
            $entityManager->flush();
            $this->addFlash('warning', 'destination.state.flash.deactivated_has_children');
        }

        return $this->redirectToRoute('destination_state_index');
    }
}
