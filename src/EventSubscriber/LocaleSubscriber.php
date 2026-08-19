<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class LocaleSubscriber implements EventSubscriberInterface
{
    private const SUPPORTED_LOCALES = ['de', 'en'];
    private const WEB_ONLY_PATHS = ['/api', '/pwa'];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $pathInfo = $request->getPathInfo();

        foreach (self::WEB_ONLY_PATHS as $prefix) {
            if (str_starts_with($pathInfo, $prefix)) {
                return;
            }
        }

        if (!$request->hasSession()) {
            return;
        }

        $locale = $request->getSession()->get('locale');
        if (\is_string($locale) && \in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $request->setLocale($locale);
        }
    }
}
