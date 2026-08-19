<?php

namespace App\Modules\Default\Controller;

use App\Modules\User\Entity\Permission;
use App\Modules\User\Entity\Role;
use App\Modules\User\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Default controller for homepage and dashboard.
 */
class DefaultController extends AbstractController
{
    /**
     * Homepage route - redirects to login page.
     */
    #[Route('/', name: 'homepage')]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('app_login');
    }
    
    /**
     * Dashboard page - main application dashboard.
     */
    #[Route('/dashboard', name: 'app_dashboard')]
    public function dashboard(EntityManagerInterface $entityManager): Response
    {
        return $this->render('@Default/dashboard.html.twig', [
            'userCount' => $entityManager->getRepository(UserEntity::class)->count([]),
            'roleCount' => $entityManager->getRepository(Role::class)->count([]),
            'permissionCount' => $entityManager->getRepository(Permission::class)->count([]),
        ]);
    }
}
