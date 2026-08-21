<?php

namespace App\Modules\SearchSource\Command;

use App\Modules\Default\Entity\Menu;
use App\Modules\Default\Entity\MenuCategory;
use App\Modules\SearchSource\Controller\SearchSourceController;
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

#[AsCommand(name: 'app:search-source:seed-admin', description: 'Seed Search Source admin permissions and menu entries.')]
class SeedSearchSourceAdminCommand extends Command
{
    /**
     * @var array<string, array{label: string, route: string, controller: class-string, action: string}>
     */
    private const PERMISSIONS = [
        'search_source.view' => ['label' => 'search_source.view', 'route' => 'search_source_index', 'controller' => SearchSourceController::class, 'action' => 'index'],
        'search_source.detail' => ['label' => 'search_source.detail', 'route' => 'search_source_show', 'controller' => SearchSourceController::class, 'action' => 'show'],
        'search_source.create' => ['label' => 'search_source.create', 'route' => 'search_source_new', 'controller' => SearchSourceController::class, 'action' => 'new'],
        'search_source.update' => ['label' => 'search_source.update', 'route' => 'search_source_edit', 'controller' => SearchSourceController::class, 'action' => 'edit'],
        'search_source.delete' => ['label' => 'search_source.delete', 'route' => 'search_source_delete', 'controller' => SearchSourceController::class, 'action' => 'delete'],
    ];

    /**
     * @var array<int, array{route: string, name: string, icon: string, position: int, permission: string}>
     */
    private const MENU_DEFINITIONS = [
        [
            'name' => 'search_source.navigation.sources',
            'route' => 'search_source_index',
            'icon' => 'solar:database-bold',
            'position' => 10,
            'permission' => 'search_source.view',
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
        $category = $this->dataSourcesCategory($categoryRepository);
        $this->seedMenus($menuRepository, $category, $permissions);
        $this->assignToSuperAdmin($roleRepository, $permissions);

        $this->entityManager->flush();

        $io->success('Search Source admin permissions and menus seeded.');

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
    private function dataSourcesCategory(ObjectRepository $categoryRepository): MenuCategory
    {
        $category = $categoryRepository->findOneBy(['code' => 'data_sources']);
        if ($category instanceof MenuCategory) {
            return $category;
        }

        $category = new MenuCategory();
        $category->setName('Data Sources');
        $category->setCode('data_sources');
        $category->setLabelKey('search_source.navigation.data_sources');
        $category->setIcon('solar:database-bold');
        $category->setPosition(25);
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
            $menu->setCategory('data_sources');
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
