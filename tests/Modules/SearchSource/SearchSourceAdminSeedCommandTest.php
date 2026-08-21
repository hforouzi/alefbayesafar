<?php

namespace App\Tests\Modules\SearchSource;

use App\Modules\Default\Entity\Menu;
use App\Modules\User\Entity\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class SearchSourceAdminSeedCommandTest extends KernelTestCase
{
    public function testSeedCommandCreatesSearchSourcePermissionsAndMenusIdempotently(): void
    {
        self::bootKernel();
        self::assertNotNull(self::$kernel);

        $application = new Application(self::$kernel);
        $command = $application->find('app:search-source:seed-admin');

        $firstRun = new CommandTester($command);
        self::assertSame(0, $firstRun->execute([]));

        $secondRun = new CommandTester($command);
        self::assertSame(0, $secondRun->execute([]));

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        $permission = $em->getRepository(Permission::class)->findOneBy(['route' => 'search_source_index']);
        $menu = $em->getRepository(Menu::class)->findOneBy(['route' => 'search_source_index']);

        self::assertInstanceOf(Permission::class, $permission);
        self::assertInstanceOf(Menu::class, $menu);
        self::assertSame('search_source.navigation.sources', $menu->getName());
    }
}
