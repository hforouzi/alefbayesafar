<?php
// src/App/Modules/Default/Controller/MenuController.php

namespace App\Modules\Default\Controller;

use App\Modules\Default\Entity\Menu;
use App\Modules\Default\Form\MenuType;
use App\Modules\Default\Repository\MenuRepository;
use App\Modules\Default\Service\MenuService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/menu')]
class MenuController extends AbstractController
{
    public function __construct(
        private readonly MenuService $menuService
    ) {
    }
    
    #[Route('/', name: 'menu_index', methods: ['GET'])]
    public function index(MenuRepository $menuRepository): Response
    {
        $flatMenus = $this->menuService->getFlatMenuItemsWithHierarchy();
        return $this->render('@Default/menu/index.html.twig', [
            'menus' => $flatMenus,
        ]);
    }
    
    // ... بقیه اکشن ها (new, edit, delete) بدون تغییر باقی می مانند ...
    #[Route('/new', name: 'menu_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $menu = new Menu();
        $form = $this->createForm(MenuType::class, $menu);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($menu);
            $entityManager->flush();
            
            $this->addFlash('success', 'Menu created successfully!');
            
            return $this->redirectToRoute('menu_index');
        }
        return $this->render('@Default/menu/new.html.twig', [
            'menu' => $menu,
            'form' => $form->createView(),
        ]);
    }
    
    
    
    #[Route('/{id}/edit', name: 'menu_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Menu $menu, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MenuType::class, $menu);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            
            $this->addFlash('success', 'Menu updated successfully!');
            
            return $this->redirectToRoute('menu_index');
        }
        
        return $this->render('@Default/menu/edit.html.twig', [
            'menu' => $menu,
            'form' => $form->createView(),
        
        ]);
    }
    
    #[Route('/{id}/delete', name: 'menu_delete', methods: ['POST','GET'])]
    public function delete(Request $request, Menu $menu, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$menu->getId(), $request->request->get('_token'))) {
            $entityManager->remove($menu);
            $entityManager->flush();
            
            $this->addFlash('success', 'Menu deleted successfully!');
        }
        
        return $this->redirectToRoute('menu_index');
    }
}
