<?php
namespace App\Modules\User\Controller;

use App\Modules\User\Repository\ControllerActionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ManageControllerActionController extends AbstractController
{
    #[Route('/manage-actions', name: 'manage_actions')]
    public function manageActions(ControllerActionRepository $controllerActionRepository): Response
    {
        $actions = $controllerActionRepository->findAll();
        
        return $this->render('@User/actions/manage.html.twig', [
            'actions' => $actions,
        ]);
    }
}