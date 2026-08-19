<?php

namespace App\Tests\Smoke;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

class ApplicationSmokeTest extends WebTestCase
{
    public function testPublicHomepageIsAccessible(): void
    {
        $client = self::createClient();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'پکیج سفر');
    }

    public function testBuildWizardIsAccessible(): void
    {
        $client = self::createClient();

        $client->request('GET', '/build');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'ساخت پکیج سفر');
        self::assertSelectorExists('[data-controller~="trip-wizard"]');
    }

    public function testTripsPlaceholderIsAccessible(): void
    {
        $client = self::createClient();

        $client->request('GET', '/trips');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'هنوز سفری ذخیره نکرده‌اید');
    }

    public function testLoginPageIsAccessible(): void
    {
        $client = self::createClient();

        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
    }

    public function testAnonymousDashboardRequestRedirectsToLogin(): void
    {
        $client = self::createClient();

        $client->request('GET', '/dashboard');

        self::assertResponseRedirects('/login');
    }

    public function testGenericDashboardRouteExists(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get(RouterInterface::class);

        self::assertNotNull($router->getRouteCollection()->get('app_dashboard'));
    }

    public function testPublicRoutesExist(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get(RouterInterface::class);

        self::assertNotNull($router->getRouteCollection()->get('homepage'));
        self::assertNotNull($router->getRouteCollection()->get('public_build'));
        self::assertNotNull($router->getRouteCollection()->get('public_trips'));
        self::assertNotNull($router->getRouteCollection()->get('app_login'));
    }
}
