<?php

namespace App\Tests\Modules\TripPlanner;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

class TripPlannerAdminTest extends WebTestCase
{
    public function testTripPlannerTestRouteExistsAndRendersDebugForm(): void
    {
        $client = self::createClient();
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);
        self::assertNotNull($router->getRouteCollection()->get('trip_planner_test'));

        $client->request('GET', '/admin/trip-planner/test/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Trip Planner Test');
        self::assertSelectorExists('form[data-turbo="false"]');
        self::assertSelectorTextContains('body', 'Run planner test');
    }
}
