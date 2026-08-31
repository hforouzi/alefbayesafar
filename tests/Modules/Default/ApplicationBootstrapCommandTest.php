<?php

namespace App\Tests\Modules\Default;

use App\Modules\Default\Entity\AppSetting;
use App\Modules\Default\Entity\Menu;
use App\Modules\Default\Entity\MenuCategory;
use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\User\Entity\Permission;
use App\Modules\User\Entity\Role;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ApplicationBootstrapCommandTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $this->clearBootstrapTables();
    }

    public function testBootstrapCreatesRequiredBaselineAndIsIdempotent(): void
    {
        $firstRun = $this->runBootstrap();
        self::assertSame(0, $firstRun->getStatusCode());
        self::assertStringContainsString('Bootstrap completed successfully', $firstRun->getDisplay());

        $em = $this->entityManager();
        self::assertNotNull($em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_SUPER_ADMIN']));
        self::assertNotNull($em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_ADMIN']));
        self::assertNotNull($em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_USER']));

        foreach ([
            'destination_country_index',
            'search_source_index',
            'hotel_index',
            'hotel_amenity_index',
            'flight_offer_index',
            'flight_airline_index',
            'flight_external_test',
        ] as $route) {
            self::assertNotNull($em->getRepository(Menu::class)->findOneBy(['route' => $route]), $route);
        }

        foreach ([
            'destination.country.view',
            'search_source.view',
            'hotel.offer.search',
            'hotel.rate.view',
            'hotel.room_type.view',
            'flight.offer.view',
            'flight.external_test.view',
        ] as $permissionName) {
            self::assertNotNull($em->getRepository(Permission::class)->findOneBy(['name' => $permissionName]), $permissionName);
        }

        self::assertNotNull($em->getRepository(MenuCategory::class)->findOneBy(['code' => 'catalog']));
        self::assertNotNull($em->getRepository(MenuCategory::class)->findOneBy(['code' => 'flight_commerce']));
        self::assertNotNull($em->getRepository(MenuCategory::class)->findOneBy(['code' => 'data_sources']));
        self::assertSame('AlefBayeSafar', $em->getRepository(AppSetting::class)->findOneBy(['name' => 'site_name'])?->getValue());
        self::assertSame(0, $em->getRepository(SearchSource::class)->count([]));
        self::assertSame(0, $em->getRepository(Hotel::class)->count([]));
        self::assertSame(0, $em->getRepository(FlightOffer::class)->count([]));

        $counts = $this->baselineCounts();
        $secondRun = $this->runBootstrap();
        self::assertSame(0, $secondRun->getStatusCode());
        self::assertSame($counts, $this->baselineCounts());
    }

    public function testBootstrapPreservesExistingSettingAndCustomRoles(): void
    {
        $em = $this->entityManager();
        $setting = (new AppSetting())->setName('site_name')->setValue('Custom Travel');
        $customRole = (new Role())->setName('ROLE_CUSTOM_EDITOR');
        $em->persist($setting);
        $em->persist($customRole);
        $em->flush();

        $run = $this->runBootstrap();

        self::assertSame(0, $run->getStatusCode());
        self::assertSame('Custom Travel', $em->getRepository(AppSetting::class)->findOneBy(['name' => 'site_name'])?->getValue());
        self::assertNotNull($em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_CUSTOM_EDITOR']));
    }

    public function testDryRunDoesNotMutateDatabase(): void
    {
        $before = $this->baselineCounts();
        $run = $this->runBootstrap(['--dry-run' => true]);

        self::assertSame(0, $run->getStatusCode());
        self::assertStringContainsString('Dry-run completed successfully', $run->getDisplay());
        self::assertSame($before, $this->baselineCounts());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function runBootstrap(array $options = []): CommandTester
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:bootstrap');
        $tester = new CommandTester($command);
        $tester->execute($options);

        return $tester;
    }

    /**
     * @return array<string, int>
     */
    private function baselineCounts(): array
    {
        $em = $this->entityManager();

        return [
            'roles' => $em->getRepository(Role::class)->count([]),
            'permissions' => $em->getRepository(Permission::class)->count([]),
            'menus' => $em->getRepository(Menu::class)->count([]),
            'menuCategories' => $em->getRepository(MenuCategory::class)->count([]),
            'settings' => $em->getRepository(AppSetting::class)->count([]),
            'searchSources' => $em->getRepository(SearchSource::class)->count([]),
            'hotels' => $em->getRepository(Hotel::class)->count([]),
            'flightOffers' => $em->getRepository(FlightOffer::class)->count([]),
        ];
    }

    private function clearBootstrapTables(): void
    {
        $connection = $this->entityManager()->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'menu_permissions',
            'role_permission',
            'permission_controller_action',
            'menu',
            'menu_category',
            'permission',
            'controller_action',
            'role',
            'app_setting',
            'search_source',
            'hotel_offer',
            'hotel_rate',
            'hotel_room_type',
            'hotel_image',
            'hotel_source_reference',
            'hotel',
            'flight_offer_leg',
            'flight_offer',
            'airline',
        ] as $table) {
            $this->truncateIfExists($connection, $table);
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->entityManager()->clear();
    }

    private function truncateIfExists(Connection $connection, string $table): void
    {
        try {
            $connection->executeStatement('TRUNCATE TABLE ' . $table);
        } catch (\Throwable) {
            // Some future branches may not have every optional table yet.
        }
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
