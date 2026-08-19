<?php

namespace App\Tests\Smoke;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

class ApplicationSmokeTest extends WebTestCase
{
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
}
