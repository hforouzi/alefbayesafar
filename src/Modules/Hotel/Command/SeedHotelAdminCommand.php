<?php

namespace App\Modules\Hotel\Command;

use App\Modules\Default\Entity\Menu;
use App\Modules\Default\Entity\MenuCategory;
use App\Modules\Hotel\Controller\HotelAmenityController;
use App\Modules\Hotel\Controller\HotelController;
use App\Modules\Hotel\Controller\HotelImageController;
use App\Modules\Hotel\Controller\HotelSearchController;
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

#[AsCommand(name: 'app:hotel:seed-admin', description: 'Seed Hotel admin permissions and menu entries.')]
class SeedHotelAdminCommand extends Command
{
    /**
     * @var array<string, array{label: string, route: string, controller: class-string, action: string}>
     */
    private const PERMISSIONS = [
        'hotel.view' => ['label' => 'hotel.view', 'route' => 'hotel_index', 'controller' => HotelController::class, 'action' => 'index'],
        'hotel.detail' => ['label' => 'hotel.detail', 'route' => 'hotel_show', 'controller' => HotelController::class, 'action' => 'show'],
        'hotel.create' => ['label' => 'hotel.create', 'route' => 'hotel_new', 'controller' => HotelController::class, 'action' => 'new'],
        'hotel.update' => ['label' => 'hotel.update', 'route' => 'hotel_edit', 'controller' => HotelController::class, 'action' => 'edit'],
        'hotel.delete' => ['label' => 'hotel.delete', 'route' => 'hotel_delete', 'controller' => HotelController::class, 'action' => 'delete'],
        'hotel.search' => ['label' => 'hotel.search', 'route' => 'hotel_search', 'controller' => HotelSearchController::class, 'action' => 'search'],
        'hotel.import' => ['label' => 'hotel.import', 'route' => 'hotel_search_import', 'controller' => HotelSearchController::class, 'action' => 'import'],
        'hotel.image.create' => ['label' => 'hotel.image.create', 'route' => 'hotel_image_new', 'controller' => HotelImageController::class, 'action' => 'new'],
        'hotel.image.update' => ['label' => 'hotel.image.update', 'route' => 'hotel_image_edit', 'controller' => HotelImageController::class, 'action' => 'edit'],
        'hotel.image.delete' => ['label' => 'hotel.image.delete', 'route' => 'hotel_image_delete', 'controller' => HotelImageController::class, 'action' => 'delete'],
        'hotel.amenity.view' => ['label' => 'hotel.amenity.view', 'route' => 'hotel_amenity_index', 'controller' => HotelAmenityController::class, 'action' => 'index'],
        'hotel.amenity.create' => ['label' => 'hotel.amenity.create', 'route' => 'hotel_amenity_new', 'controller' => HotelAmenityController::class, 'action' => 'new'],
        'hotel.amenity.update' => ['label' => 'hotel.amenity.update', 'route' => 'hotel_amenity_edit', 'controller' => HotelAmenityController::class, 'action' => 'edit'],
        'hotel.amenity.delete' => ['label' => 'hotel.amenity.delete', 'route' => 'hotel_amenity_delete', 'controller' => HotelAmenityController::class, 'action' => 'delete'],
    ];

    /**
     * @var array<int, array{route: string, name: string, icon: string, position: int, permission: string}>
     */
    private const MENU_DEFINITIONS = [
        [
            'name' => 'hotel.navigation.hotels',
            'route' => 'hotel_index',
            'icon' => 'solar:buildings-bold',
            'position' => 70,
            'permission' => 'hotel.view',
        ],
        [
            'name' => 'hotel.navigation.amenities',
            'route' => 'hotel_amenity_index',
            'icon' => 'solar:star-bold',
            'position' => 80,
            'permission' => 'hotel.amenity.view',
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
        $catalogCategory = $this->catalogCategory($categoryRepository);
        $this->seedMenus($menuRepository, $catalogCategory, $permissions);
        $this->assignToSuperAdmin($roleRepository, $permissions);

        $this->entityManager->flush();

        $io->success('Hotel admin permissions and menus seeded.');

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
    private function catalogCategory(ObjectRepository $categoryRepository): MenuCategory
    {
        $category = $categoryRepository->findOneBy(['code' => 'catalog']);
        if ($category instanceof MenuCategory) {
            return $category;
        }

        $category = new MenuCategory();
        $category->setName('Catalog');
        $category->setCode('catalog');
        $category->setLabelKey('destination.navigation.catalog');
        $category->setIcon('solar:map-bold');
        $category->setPosition(15);
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
            $menu->setCategory('catalog');
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
