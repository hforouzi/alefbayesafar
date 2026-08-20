<?php

namespace App\Modules\Auth\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
    private const SUPPORTED_LOCALES = ['fa', 'en'];

    #[Route('/locale/{locale}', name: 'app_locale_switch')]
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (!\in_array($locale, self::SUPPORTED_LOCALES, true)) {
            return $this->redirectToRoute('app_dashboard');
        }

        $request->getSession()->set('locale', $locale);
        $request->setLocale($locale);

        $referer = $request->headers->get('referer');
        if (\is_string($referer) && $this->isSafeReferer($request, $referer)) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_dashboard');
    }

    private function isSafeReferer(Request $request, string $referer): bool
    {
        $refererParts = parse_url($referer);
        if (!\is_array($refererParts) || !isset($refererParts['host'])) {
            return false;
        }

        $currentHost = $request->getHost();

        return $refererParts['host'] === $currentHost;
    }
}
