<?php

namespace App\Modules\Flight\Command;

use App\Modules\Default\Entity\Menu;
use App\Modules\Default\Entity\MenuCategory;
use App\Modules\Flight\Controller\AirlineController;
use App\Modules\Flight\Controller\ExternalFlightTestController;
use App\Modules\Flight\Controller\FlightLookupController;
use App\Modules\Flight\Controller\FlightOfferController;
use App\Modules\Flight\Controller\FlightOfferLegController;
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

#[AsCommand(name: 'app:flight:seed-admin', description: 'Seed Flight Commerce admin permissions and menu entries.')]
class SeedFlightAdminCommand extends Command
{
    /**
     * @var array<string, array{label: string, route: string, controller: class-string, action: string}>
     */
    private const PERMISSIONS = [
        'flight.airline.view' => ['label' => 'flight.airline.view', 'route' => 'flight_airline_index', 'controller' => AirlineController::class, 'action' => 'index'],
        'flight.airline.create' => ['label' => 'flight.airline.create', 'route' => 'flight_airline_new', 'controller' => AirlineController::class, 'action' => 'new'],
        'flight.airline.update' => ['label' => 'flight.airline.update', 'route' => 'flight_airline_edit', 'controller' => AirlineController::class, 'action' => 'edit'],
        'flight.airline.delete' => ['label' => 'flight.airline.delete', 'route' => 'flight_airline_delete', 'controller' => AirlineController::class, 'action' => 'delete'],
        'flight.lookup.airlines' => ['label' => 'flight.lookup.view', 'route' => 'flight_lookup_airlines', 'controller' => FlightLookupController::class, 'action' => 'airlines'],
        'flight.external_test.view' => ['label' => 'flight.external_test.view', 'route' => 'flight_external_test', 'controller' => ExternalFlightTestController::class, 'action' => '__invoke'],
        'flight.offer.view' => ['label' => 'flight.offer.view', 'route' => 'flight_offer_index', 'controller' => FlightOfferController::class, 'action' => 'index'],
        'flight.offer.create' => ['label' => 'flight.offer.create', 'route' => 'flight_offer_new', 'controller' => FlightOfferController::class, 'action' => 'new'],
        'flight.offer.update' => ['label' => 'flight.offer.update', 'route' => 'flight_offer_edit', 'controller' => FlightOfferController::class, 'action' => 'edit'],
        'flight.offer.toggle' => ['label' => 'flight.offer.toggle', 'route' => 'flight_offer_toggle', 'controller' => FlightOfferController::class, 'action' => 'toggle'],
        'flight.leg.view' => ['label' => 'flight.leg.view', 'route' => 'flight_offer_leg_index', 'controller' => FlightOfferLegController::class, 'action' => 'index'],
        'flight.leg.create' => ['label' => 'flight.leg.create', 'route' => 'flight_offer_leg_new', 'controller' => FlightOfferLegController::class, 'action' => 'new'],
        'flight.leg.update' => ['label' => 'flight.leg.update', 'route' => 'flight_offer_leg_edit', 'controller' => FlightOfferLegController::class, 'action' => 'edit'],
        'flight.leg.delete' => ['label' => 'flight.leg.delete', 'route' => 'flight_offer_leg_delete', 'controller' => FlightOfferLegController::class, 'action' => 'delete'],
    ];

    /**
     * @var array<int, array{route: string, name: string, icon: string, position: int, permission: string}>
     */
    private const MENU_DEFINITIONS = [
        [
            'name' => 'flight.navigation.own_deals',
            'route' => 'flight_offer_index',
            'icon' => 'solar:plane-bold',
            'position' => 10,
            'permission' => 'flight.offer.view',
        ],
        [
            'name' => 'flight.navigation.airlines',
            'route' => 'flight_airline_index',
            'icon' => 'solar:compass-bold',
            'position' => 20,
            'permission' => 'flight.airline.view',
        ],
        [
            'name' => 'flight.navigation.external_test',
            'route' => 'flight_external_test',
            'icon' => 'solar:magnifer-bold',
            'position' => 30,
            'permission' => 'flight.external_test.view',
        ],
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
        $categoryRepository = $this->entityManager->getRepository(MenuCategory::class);
        $menuRepository = $this->entityManager->getRepository(Menu::class);
        $roleRepository = $this->entityManager->getRepository(Role::class);

        $permissions = $this->seedPermissions($permissionRepository, $controllerActionRepository);
        $category = $this->flightCommerceCategory($categoryRepository);
        $this->seedMenus($menuRepository, $category, $permissions);
        $this->assignToSuperAdmin($roleRepository, $permissions);

        $this->entityManager->flush();

        $io->success('Flight Commerce admin permissions and menus seeded.');

        return Command::SUCCESS;
    }

    /**
     * @param ObjectRepository<Permission> $permissionRepository
     * @param ObjectRepository<ControllerAction> $controllerActionRepository
     *
     * @return array<string, Permission>
     */
    private function seedPermissions(ObjectRepository $permissionRepository, ObjectRepository $controllerActionRepository): array
    {
        $permissions = [];
        foreach (self::PERMISSIONS as $code => $definition) {
            $permission = $permissionRepository->findOneBy(['route' => $definition['route']]);
            if (!$permission instanceof Permission) {
                $permission = $permissionRepository->findOneBy(['name' => $definition['label']]);
            }
            if (!$permission instanceof Permission) {
                $permission = new Permission();
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
            $permissions[$code] = $permission;
        }

        return $permissions;
    }

    /**
     * @param ObjectRepository<MenuCategory> $categoryRepository
     */
    private function flightCommerceCategory(ObjectRepository $categoryRepository): MenuCategory
    {
        $category = $categoryRepository->findOneBy(['code' => 'flight_commerce']);
        if ($category instanceof MenuCategory) {
            return $category;
        }

        $category = new MenuCategory();
        $category->setName('Flight Commerce');
        $category->setCode('flight_commerce');
        $category->setLabelKey('flight.navigation.commerce');
        $category->setIcon('solar:plane-bold');
        $category->setPosition(22);
        $category->setActive(true);
        $this->entityManager->persist($category);

        return $category;
    }

    /**
     * @param ObjectRepository<Menu> $menuRepository
     * @param array<string, Permission> $permissions
     */
    private function seedMenus(ObjectRepository $menuRepository, MenuCategory $category, array $permissions): void
    {
        foreach (self::MENU_DEFINITIONS as $definition) {
            if ($this->router->getRouteCollection()->get($definition['route']) === null) {
                continue;
            }

            $menu = $menuRepository->findOneBy(['route' => $definition['route']]);
            if (!$menu instanceof Menu) {
                $menu = new Menu();
                $this->entityManager->persist($menu);
            }

            $menu->setName($definition['name']);
            $menu->setRoute($definition['route']);
            $menu->setIcon($definition['icon']);
            $menu->setPosition($definition['position']);
            $menu->setCategory('flight_commerce');
            $menu->setMenuCategory($category);
            $menu->setParent(null);
            $menu->getPermissions()->clear();

            $permission = $permissions[$definition['permission']] ?? null;
            if ($permission instanceof Permission) {
                $menu->addPermission($permission);
            }
        }
    }

    /**
     * @param ObjectRepository<Role> $roleRepository
     * @param array<string, Permission> $permissions
     */
    private function assignToSuperAdmin(ObjectRepository $roleRepository, array $permissions): void
    {
        $role = $roleRepository->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);
        if (!$role instanceof Role) {
            return;
        }

        foreach ($permissions as $permission) {
            $role->addPermission($permission);
        }
    }
}
