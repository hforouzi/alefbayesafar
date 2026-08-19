<?php

namespace App\Modules\User\Service;

use Symfony\Component\Routing\RouterInterface;

class ControllerScanner
{
    private RouterInterface $router;
    
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }
    
    public function getControllers(): array
    {
        $controllers = [];
        $actions = [];
        $routes = $this->router->getRouteCollection();
        
        foreach ($routes as $route) {
            $controller = $route->getDefault('_controller');
            
            if ($controller) {
                // Check if controller string is in the format of Class::method
                if (is_string($controller) && strpos($controller, '::') !== false) {
                    [$controllerClass, $method] = explode('::', $controller);
                    
                    // Ensure the controller class exists
                    if (class_exists($controllerClass)) {
                        $reflectionClass = new \ReflectionClass($controllerClass);
                        if (!isset($controllers[$controllerClass])) {
                            $controllers[$controllerClass] = [];
                        }
                        
                        // Ensure the method exists
                        if ($reflectionClass->hasMethod($method)) {
                            $actions[] = [
                                'controller' => $controllerClass,
                                'action' => $method
                            ];
                        }
                    }
                }
            }
        }
        
        return $actions;
    }
}
