<?php
namespace App\Modules\User\Service;

use App\Modules\User\Entity\ControllerAction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Route;
class ControllerActionService
{
    private EntityManagerInterface $entityManager;
    private RouterInterface $router;
    
    public function __construct(EntityManagerInterface $entityManager, RouterInterface $router)
    {
        $this->entityManager = $entityManager;
        $this->router = $router;
    }
    
    public function syncControllerActions(): void
    {
        foreach ($this->scanControllerActions() as $action) {
            $existingAction = $this->entityManager->getRepository(ControllerAction::class)
                ->findOneBy([
                    'controller' => $action['controller'],
                    'action' => $action['action'],
                ]);
            
            if (!$existingAction) {
                $controllerAction = new ControllerAction();
                $controllerAction->setController($action['controller']);
                $controllerAction->setAction($action['action']);
                
                $this->entityManager->persist($controllerAction);
            }
        }
        
        $this->entityManager->flush();
    }

    /**
     * @return array<int, array{controller: string, action: string}>
     */
    public function findUnsyncedActions(): array
    {
        $existingActions = [];
        foreach ($this->entityManager->getRepository(ControllerAction::class)->findAll() as $action) {
            $existingActions[$this->buildActionKey($action->getController(), $action->getAction())] = true;
        }

        $unsyncedActions = [];
        foreach ($this->scanControllerActions() as $action) {
            if (!isset($existingActions[$this->buildActionKey($action['controller'], $action['action'])])) {
                $unsyncedActions[] = $action;
            }
        }

        return $unsyncedActions;
    }

    /**
     * @return array<int, array{controller: string, action: string}>
     */
    private function scanControllerActions(): array
    {
        $actions = [];
        $seenActions = [];
        $routes = $this->router->getRouteCollection()->all();
        
        foreach ($routes as $route) {
            $controller = $this->getControllerFromRoute($route);
            if (!$controller || !str_contains($controller, '::')) {
                continue;
            }

            [
                $controllerClass,
                $actionMethod
            ] = explode('::', $controller, 2);

            if (str_contains($controllerClass, 'web_profiler')) {
                continue;
            }

            $actionKey = $this->buildActionKey($controllerClass, $actionMethod);
            if (!isset($seenActions[$actionKey])) {
                $actions[] = [
                    'controller' => $controllerClass,
                    'action' => $actionMethod,
                ];
                $seenActions[$actionKey] = true;
            }
        }

        return $actions;
    }

    private function buildActionKey(?string $controller, ?string $action): string
    {
        return sprintf('%s::%s', $controller ?? '', $action ?? '');
    }
    
    private function getControllerFromRoute(Route $route): ?string
    {
        return $route->getDefault('_controller');
    }
}
