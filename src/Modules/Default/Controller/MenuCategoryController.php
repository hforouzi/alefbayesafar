<?php

namespace App\Modules\Default\Controller;

use App\Modules\Default\Entity\MenuCategory;
use App\Modules\Default\Form\MenuCategoryType;
use App\Modules\Default\Repository\MenuCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/menu-categories')]
class MenuCategoryController extends AbstractController
{
    #[Route('/', name: 'menu_category_index', methods: ['GET'])]
    public function index(MenuCategoryRepository $repository): Response
    {
        return $this->render('@Default/menu_category/index.html.twig', [
            'menuCategories' => $repository->findBy([], ['position' => 'ASC', 'name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'menu_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $menuCategory = new MenuCategory();
        $form = $this->createForm(MenuCategoryType::class, $menuCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($menuCategory);
            $entityManager->flush();

            $this->addFlash('success', 'menu_category.flash.created');

            return $this->redirectToRoute('menu_category_index');
        }

        return $this->render('@Default/menu_category/new.html.twig', [
            'menuCategory' => $menuCategory,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'menu_category_show', methods: ['GET'])]
    public function show(MenuCategory $menuCategory): Response
    {
        return $this->render('@Default/menu_category/show.html.twig', [
            'menuCategory' => $menuCategory,
        ]);
    }

    #[Route('/{id}/edit', name: 'menu_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, MenuCategory $menuCategory, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MenuCategoryType::class, $menuCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'menu_category.flash.updated');

            return $this->redirectToRoute('menu_category_index');
        }

        return $this->render('@Default/menu_category/edit.html.twig', [
            'menuCategory' => $menuCategory,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'menu_category_delete', methods: ['POST'])]
    public function delete(Request $request, MenuCategory $menuCategory, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_menu_category_' . $menuCategory->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('menu_category_index');
        }

        if ($menuCategory->getMenus()->count() > 0) {
            $this->addFlash('warning', 'menu_category.flash.delete_blocked');

            return $this->redirectToRoute('menu_category_index');
        }

        $entityManager->remove($menuCategory);
        $entityManager->flush();

        $this->addFlash('success', 'menu_category.flash.deleted');

        return $this->redirectToRoute('menu_category_index');
    }
}
