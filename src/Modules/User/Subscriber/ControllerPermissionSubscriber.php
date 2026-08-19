<?php

namespace App\Modules\User\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Modules\User\Service\PermissionChecker;

class ControllerPermissionSubscriber implements EventSubscriberInterface
{
    private const PUBLIC_ROUTE_NAMES = [
        'homepage',
        'app_login',
        'app_logout',
        'app_captcha',
        'app_locale_switch',
    ];

    private $permissionChecker;
    private $router;
    private $security;
    private $translator;
    
    public function __construct(
        PermissionChecker $permissionChecker,
        RouterInterface $router,
        Security $security,
        TranslatorInterface $translator
    )
    {
        $this->permissionChecker = $permissionChecker;
        $this->router = $router;
        $this->security = $security;
        $this->translator = $translator;
    }
    
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }
    
    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if (in_array($request->attributes->get('_route'), self::PUBLIC_ROUTE_NAMES, true)) {
            return; // Skip permission check for public endpoints.
        }
        $controller = $event->getController();
        
        // Check if the controller is an instance of AbstractController
        if (!is_array($controller) || !$controller[0] instanceof \Symfony\Bundle\FrameworkBundle\Controller\AbstractController) {
            return;
        }
        
        $controllerInstance = $controller[0];
        $controllerName = (new \ReflectionClass($controllerInstance))->getName();
        $actionName = $controller[1];
        // Check permissions
        if (!$this->permissionChecker->hasPermission($controllerName, $actionName)) {
            
            throw new AccessDeniedException('You do not have permission to access this action.');
        }
    }
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$this->isAccessDeniedException($exception)) {
            return;
        }

        $request = $event->getRequest();
        $message = $this->translator->trans('security.access_denied.message');

        if ($this->isJsonOrApiRequest($request)) {
            $event->setResponse(new JsonResponse([
                'success' => false,
                'error' => 'access_denied',
                'message' => $message,
            ], 403));
            return;
        }

        if ($request->hasSession()) {
            $session = $request->getSession();
            $session->getFlashBag()->add('error', 'security.access_denied.message');
            $session->save();
        }

        $event->setResponse(new RedirectResponse($this->resolveSafeRedirectTarget($request)));
    }

    private function isAccessDeniedException(\Throwable $exception): bool
    {
        if ($exception instanceof AccessDeniedException || $exception instanceof AccessDeniedHttpException) {
            return true;
        }

        return $exception instanceof HttpExceptionInterface && 403 === $exception->getStatusCode();
    }

    private function isJsonOrApiRequest(Request $request): bool
    {
        if ($request->isXmlHttpRequest() || str_starts_with($request->getPathInfo(), '/api')) {
            return true;
        }

        $contentType = (string) $request->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            return true;
        }

        $accept = (string) $request->headers->get('Accept', '');
        if (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) {
            return true;
        }

        return false;
    }

    private function resolveSafeRedirectTarget(Request $request): string
    {
        $currentRoute = (string) $request->attributes->get('_route');
        $preferredRoutes = [];

        if (null === $this->security->getUser()) {
            $preferredRoutes[] = 'app_login';
        } else {
            $preferredRoutes[] = 'app_dashboard';
        }

        $preferredRoutes[] = 'homepage';

        foreach ($preferredRoutes as $routeName) {
            if ($routeName === $currentRoute) {
                continue;
            }

            if (null !== $this->router->getRouteCollection()->get($routeName)) {
                return $this->router->generate($routeName);
            }
        }

        return '/';
    }
}
