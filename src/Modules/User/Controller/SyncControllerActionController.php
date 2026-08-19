<?php
namespace App\Modules\User\Controller;

use App\Modules\User\Service\ControllerActionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SyncControllerActionController extends AbstractController
{
    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }
    
    #[Route('/sync-actions', name: 'sync_actions')]
    public function syncActions(ControllerActionService $controllerActionService,Request $request): Response
    {
        $controllerActionService->syncControllerActions();
        return new RedirectResponse($request->headers->get('referer'));
    }
}