<?php

namespace App\Modules\SearchSource\Controller;

use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Form\SearchSourceType;
use App\Modules\SearchSource\Repository\SearchSourceRepository;
use App\Modules\SearchSource\Service\SearchSourceAdminFilterLabelResolver;
use App\Modules\SearchSource\Service\SearchSourceAdminListRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/data-sources/sources')]
class SearchSourceController extends AbstractController
{
    #[Route('/', name: 'search_source_index', methods: ['GET'])]
    public function index(
        Request $request,
        SearchSourceRepository $sourceRepository,
        SearchSourceAdminListRequest $adminListRequest,
        SearchSourceAdminFilterLabelResolver $labelResolver,
    ): Response {
        $filters = $adminListRequest->filters($request);
        $sources = $sourceRepository->findForAdminPage($filters);

        return $this->render('@SearchSource/source/index.html.twig', [
            'sources' => $sources->items,
            'pagination' => $sources,
            'filterLabels' => $labelResolver->labels($filters),
        ]);
    }

    #[Route('/new', name: 'search_source_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $source = new SearchSource();
        $form = $this->createForm(SearchSourceType::class, $source);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($source);
            $entityManager->flush();

            $this->addFlash('success', 'search_source.source.flash.created');

            return $this->redirectToRoute('search_source_show', ['id' => $source->getId()]);
        }

        return $this->render('@SearchSource/source/new.html.twig', [
            'source' => $source,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'search_source_show', methods: ['GET'])]
    public function show(SearchSource $source): Response
    {
        return $this->render('@SearchSource/source/show.html.twig', [
            'source' => $source,
        ]);
    }

    #[Route('/{id}/edit', name: 'search_source_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SearchSource $source, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SearchSourceType::class, $source);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'search_source.source.flash.updated');

            return $this->redirectToRoute('search_source_show', ['id' => $source->getId()]);
        }

        return $this->render('@SearchSource/source/edit.html.twig', [
            'source' => $source,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'search_source_delete', methods: ['POST'])]
    public function delete(Request $request, SearchSource $source, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_search_source_' . $source->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('search_source_index');
        }

        $source->setEnabled(!$source->isEnabled());
        $entityManager->flush();
        $this->addFlash($source->isEnabled() ? 'success' : 'warning', $source->isEnabled() ? 'search_source.source.flash.enabled' : 'search_source.source.flash.disabled');

        return $this->redirectToRoute('search_source_index');
    }
}
