<?php
namespace App\Modules\User\Controller;

use App\Modules\User\Entity\Permission;
use App\Modules\User\Form\PermissionType;
use App\Modules\User\Repository\PermissionRepository;
use App\Modules\User\Service\ControllerActionService;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class PermissionController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private TranslatorInterface $translator;
    private ControllerActionService $controllerActionService;
    private PermissionRepository $permissionRepository;
    
    public function __construct(
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        ControllerActionService $controllerActionService,
        PermissionRepository $permissionRepository
    )
    {
        $this->entityManager = $entityManager;
        $this->translator = $translator;
        $this->controllerActionService = $controllerActionService;
        $this->permissionRepository = $permissionRepository;
    }
    
    #[Route('/permissions', name: 'permission_list')]
    public function list(): Response {
        $permissionGroups = $this->permissionRepository->findGroupedForAdmin();
        $permissions = array_merge(...array_values($permissionGroups ?: [[]]));

        return $this->render('@User/permission/list.html.twig',
            [
                'permissions' => $permissions,
                'permissionGroups' => $permissionGroups,
                'unsyncedActions' => $this->controllerActionService->findUnsyncedActions(),
            ]);
    }
    
    #[Route('/permission/new', name: 'permission_new')]
    public function new(Request $request): Response {
        $permission = new Permission();
        $form = $this->createForm(PermissionType::class, $permission);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($permission);
            $this->entityManager->flush();
            
            $this->addFlash('success', $this->translator->trans('flash.success.permission_created'));
            
            return $this->redirectToRoute('permission_list');
        }
        
        // Prepare grouped actions for template
        $groupedActions = $this->prepareGroupedActions();
        
        return $this->render('@User/permission/new.html.twig',
            [
                'form' => $form->createView(),
                'new' => 1,
                'groupedActions' => $groupedActions
            ]);
    }
    
    #[Route('/permission/{id}/edit', name: 'permission_edit')]
    public function edit(Request $request, Permission $permission): Response {
        $form = $this->createForm(PermissionType::class, $permission);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', $this->translator->trans('flash.success.permission_edited'));
            
            return $this->redirectToRoute('permission_list');
        }
        
        // Prepare grouped actions for template
        $groupedActions = $this->prepareGroupedActions();
        
        return $this->render('@User/permission/edit.html.twig',
            [
                'form' => $form->createView(),
                'new' => 0,
                'groupedActions' => $groupedActions
            ]);
    }
    
    #[Route('/permission/{id}/delete', name: 'permission_delete', methods: ['POST'])]
    public function delete(Request $request, Permission $permission): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $permission->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', $this->translator->trans('permission.invalid_csrf_token'));
            
            return $this->redirectToRoute('permission_list');
        }
        
        try {
            $this->entityManager->remove($permission);
            $this->entityManager->flush();
            
            $this->addFlash('success', $this->translator->trans('permission.deleted_successfully'));
        } catch (ForeignKeyConstraintViolationException $exception) {
            $this->addFlash('danger', $this->translator->trans('permission.delete_failed_in_use'));
        } catch (Throwable $exception) {
            $this->addFlash('danger', $this->translator->trans('permission.delete_failed'));
        }
        
        return $this->redirectToRoute('permission_list');
    }
    
    private function prepareGroupedActions(): array
    {
        $controllerActions = $this->entityManager->getRepository(\App\Modules\User\Entity\ControllerAction::class)->findAll();
        $grouped = [];
        foreach ($controllerActions as $action) {
            // Extract module from controller namespace
            $controllerParts = explode('\\', $action->getController());
            $module = 'Core';
            if (count($controllerParts) >= 4 && isset($controllerParts[2])) {
                $module = $controllerParts[2];
            }
            
            // Extract controller name
            $controllerName = str_replace('Controller', '', end($controllerParts));
            
            // Group by module then controller
            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }
            if (!isset($grouped[$module][$controllerName])) {
                $grouped[$module][$controllerName] = [];
            }
            
            $grouped[$module][$controllerName][] = [
                'id' => $action->getId(),
                'controller' => $action->getController(),
                'action' => $action->getAction(),
                'controllerName' => $controllerName,
                'module' => $module
            ];
        }
        
        return $grouped;
    }
}
