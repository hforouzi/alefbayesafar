<?php

namespace App\EventSubscriber;

use App\Service\TwigPathService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber that adds module template paths to Twig.
 */
class TwigPathSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TwigPathService $twigPathService
    ) {
    }
    
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
    }
    
    /**
     * Adds module-specific template paths on kernel request.
     */
    public function onKernelRequest(): void
    {
        $this->twigPathService->addModulePaths();
    }
}
