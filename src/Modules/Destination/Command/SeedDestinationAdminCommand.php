<?php

namespace App\Modules\Destination\Command;

use App\Modules\Default\Entity\Menu;
use App\Modules\Default\Entity\MenuCategory;
use App\Modules\Destination\Controller\AirportController;
use App\Modules\Destination\Controller\CityController;
use App\Modules\Destination\Controller\CountryController;
use App\Modules\Destination\Controller\DestinationImportController;
use App\Modules\Destination\Controller\DestinationLookupController;
use App\Modules\Destination\Controller\DistrictController;
use App\Modules\Destination\Controller\StateController;
use App\Modules\User\Entity\ControllerAction;
use App\Modules\User\Entity\Permission;
use App\Modules\User\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouterInterface;

#[AsCommand(
    name: 'app:destination:seed-admin',
    description: 'Seed Destination Catalog permissions and admin menu entries.'
)]
class SeedDestinationAdminCommand extends Command
{
    /**
     * @var array<string, array{label: string, route: string, controller: class-string, action: string}>
     */
    private const PERMISSIONS = [
        'destination.country.view' => ['label' => 'destination.country.view', 'route' => 'destination_country_index', 'controller' => CountryController::class, 'action' => 'index'],
        'destination.country.create' => ['label' => 'destination.country.create', 'route' => 'destination_country_new', 'controller' => CountryController::class, 'action' => 'new'],
        'destination.country.update' => ['label' => 'destination.country.update', 'route' => 'destination_country_edit', 'controller' => CountryController::class, 'action' => 'edit'],
        'destination.country.delete' => ['label' => 'destination.country.delete', 'route' => 'destination_country_delete', 'controller' => CountryController::class, 'action' => 'delete'],
        'destination.state.view' => ['label' => 'destination.state.view', 'route' => 'destination_state_index', 'controller' => StateController::class, 'action' => 'index'],
        'destination.state.create' => ['label' => 'destination.state.create', 'route' => 'destination_state_new', 'controller' => StateController::class, 'action' => 'new'],
        'destination.state.update' => ['label' => 'destination.state.update', 'route' => 'destination_state_edit', 'controller' => StateController::class, 'action' => 'edit'],
        'destination.state.delete' => ['label' => 'destination.state.delete', 'route' => 'destination_state_delete', 'controller' => StateController::class, 'action' => 'delete'],
        'destination.city.view' => ['label' => 'destination.city.view', 'route' => 'destination_city_index', 'controller' => CityController::class, 'action' => 'index'],
        'destination.city.create' => ['label' => 'destination.city.create', 'route' => 'destination_city_new', 'controller' => CityController::class, 'action' => 'new'],
        'destination.city.update' => ['label' => 'destination.city.update', 'route' => 'destination_city_edit', 'controller' => CityController::class, 'action' => 'edit'],
        'destination.city.delete' => ['label' => 'destination.city.delete', 'route' => 'destination_city_delete', 'controller' => CityController::class, 'action' => 'delete'],
        'destination.district.view' => ['label' => 'destination.district.view', 'route' => 'destination_district_index', 'controller' => DistrictController::class, 'action' => 'index'],
        'destination.district.create' => ['label' => 'destination.district.create', 'route' => 'destination_district_new', 'controller' => DistrictController::class, 'action' => 'new'],
        'destination.district.update' => ['label' => 'destination.district.update', 'route' => 'destination_district_edit', 'controller' => DistrictController::class, 'action' => 'edit'],
        'destination.district.delete' => ['label' => 'destination.district.delete', 'route' => 'destination_district_delete', 'controller' => DistrictController::class, 'action' => 'delete'],
        'destination.airport.view' => ['label' => 'destination.airport.view', 'route' => 'destination_airport_index', 'controller' => AirportController::class, 'action' => 'index'],
        'destination.airport.create' => ['label' => 'destination.airport.create', 'route' => 'destination_airport_new', 'controller' => AirportController::class, 'action' => 'new'],
        'destination.airport.update' => ['label' => 'destination.airport.update', 'route' => 'destination_airport_edit', 'controller' => AirportController::class, 'action' => 'edit'],
        'destination.airport.delete' => ['label' => 'destination.airport.delete', 'route' => 'destination_airport_delete', 'controller' => AirportController::class, 'action' => 'delete'],
        'destination.import.view' => ['label' => 'destination.import.view', 'route' => 'destination_import_index', 'controller' => DestinationImportController::class, 'action' => 'index'],
        'destination.import.bootstrap' => ['label' => 'destination.import.bootstrap', 'route' => 'destination_import_bootstrap', 'controller' => DestinationImportController::class, 'action' => 'bootstrap'],
        'destination.import.enrichment' => ['label' => 'destination.import.enrichment', 'route' => 'destination_import_enrichment_travel_areas', 'controller' => DestinationImportController::class, 'action' => 'refreshTravelAreas'],
        'destination.import.airports' => ['label' => 'destination.import.airports', 'route' => 'destination_import_enrichment_airports', 'controller' => DestinationImportController::class, 'action' => 'refreshAirports'],
        'destination.lookup.countries' => ['label' => 'destination.lookup.view', 'route' => 'destination_lookup_countries', 'controller' => DestinationLookupController::class, 'action' => 'countries'],
        'destination.lookup.states' => ['label' => 'destination.lookup.view', 'route' => 'destination_lookup_states', 'controller' => DestinationLookupController::class, 'action' => 'states'],
        'destination.lookup.cities' => ['label' => 'destination.lookup.view', 'route' => 'destination_lookup_cities', 'controller' => DestinationLookupController::class, 'action' => 'cities'],
        'destination.lookup.districts' => ['label' => 'destination.lookup.view', 'route' => 'destination_lookup_districts', 'controller' => DestinationLookupController::class, 'action' => 'districts'],
        'destination.lookup.airports' => ['label' => 'destination.lookup.view', 'route' => 'destination_lookup_airports', 'controller' => DestinationLookupController::class, 'action' => 'airports'],
    ];

    /**
     * @var array<int, array{route: string, name: string, icon: string, position: int, permission: string}>
     */
    private const MENU_DEFINITIONS = [
        ['route' => 'destination_country_index', 'name' => 'destination.navigation.countries', 'icon' => 'solar:flag-bold', 'position' => 10, 'permission' => 'destination.country.view'],
        ['route' => 'destination_state_index', 'name' => 'destination.navigation.states', 'icon' => 'solar:map-bold', 'position' => 20, 'permission' => 'destination.state.view'],
        ['route' => 'destination_city_index', 'name' => 'destination.navigation.cities', 'icon' => 'solar:city-bold', 'position' => 30, 'permission' => 'destination.city.view'],
        ['route' => 'destination_district_index', 'name' => 'destination.navigation.districts', 'icon' => 'solar:map-point-bold', 'position' => 40, 'permission' => 'destination.district.view'],
        ['route' => 'destination_airport_index', 'name' => 'destination.navigation.airports', 'icon' => 'solar:plane-bold', 'position' => 50, 'permission' => 'destination.airport.view'],
        ['route' => 'destination_import_index', 'name' => 'destination.navigation.import', 'icon' => 'solar:download-square-bold', 'position' => 60, 'permission' => 'destination.import.view'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RouterInterface $router,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $permissionRepository = $this->entityManager->getRepository(Permission::class);
        $controllerActionRepository = $this->entityManager->getRepository(ControllerAction::class);
        $menuRepository = $this->entityManager->getRepository(Menu::class);
        $menuCategoryRepository = $this->entityManager->getRepository(MenuCategory::class);
        $roleRepository = $this->entityManager->getRepository(Role::class);
        $superAdmin = $roleRepository->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);

        $permissions = [];
        foreach (self::PERMISSIONS as $key => $definition) {
            $permission = $this->ensurePermission($permissionRepository, $controllerActionRepository, $definition);
            $permissions[$key] = $permission;

            if ($superAdmin instanceof Role && !$superAdmin->getPermissions()->contains($permission)) {
                $superAdmin->addPermission($permission);
            }
        }

        $catalogCategory = $this->ensureCatalogCategory($menuCategoryRepository);

        foreach (self::MENU_DEFINITIONS as $definition) {
            if ($this->router->getRouteCollection()->get($definition['route']) === null) {
                $io->warning(sprintf('Route "%s" was not found.', $definition['route']));
                continue;
            }

            $menu = $menuRepository->findOneBy(['route' => $definition['route']]) ?? new Menu();
            $menu->setName($definition['name']);
            $menu->setRoute($definition['route']);
            $menu->setIcon($definition['icon']);
            $menu->setCategory('catalog');
            $menu->setMenuCategory($catalogCategory);
            $menu->setPosition($definition['position']);
            $menu->setParent(null);
            $menu->getPermissions()->clear();

            $permission = $permissions[$definition['permission']] ?? null;
            if ($permission instanceof Permission) {
                $menu->addPermission($permission);
            }

            $this->entityManager->persist($menu);
        }

        $this->entityManager->flush();
        $io->success('Destination Catalog admin permissions and menu entries were seeded.');

        return Command::SUCCESS;
    }

    /**
     * @param array{label: string, route: string, controller: class-string, action: string} $definition
     * @param ObjectRepository<Permission> $permissionRepository
     * @param ObjectRepository<ControllerAction> $controllerActionRepository
     */
    private function ensurePermission(ObjectRepository $permissionRepository, ObjectRepository $controllerActionRepository, array $definition): Permission
    {
        $permission = $permissionRepository->findOneBy(['route' => $definition['route']]);
        if (!$permission instanceof Permission) {
            $permission = $permissionRepository->findOneBy(['name' => $definition['label']]) ?? new Permission();
        }

        $permission->setName($definition['label']);
        $permission->setRoute($definition['route']);

        $controllerAction = $controllerActionRepository->findOneBy([
            'controller' => $definition['controller'],
            'action' => $definition['action'],
        ]);

        if (!$controllerAction instanceof ControllerAction) {
            $controllerAction = new ControllerAction();
            $controllerAction->setController($definition['controller']);
            $controllerAction->setAction($definition['action']);
            $this->entityManager->persist($controllerAction);
        }

        if (!$permission->getControllerActions()->contains($controllerAction)) {
            $permission->addControllerAction($controllerAction);
        }

        $this->entityManager->persist($permission);

        return $permission;
    }

    /**
     * @param ObjectRepository<MenuCategory> $menuCategoryRepository
     */
    private function ensureCatalogCategory(ObjectRepository $menuCategoryRepository): MenuCategory
    {
        $category = $menuCategoryRepository->findOneBy(['code' => 'catalog']) ?? new MenuCategory();
        $category->setCode('catalog');
        $category->setName('Catalog');
        $category->setLabelKey('destination.navigation.catalog');
        $category->setIcon('solar:map-bold');
        $category->setPosition(15);
        $category->setActive(true);
        $this->entityManager->persist($category);

        return $category;
    }
}
