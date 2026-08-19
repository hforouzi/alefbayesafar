<?php

namespace App\Modules\Default\Command;

use App\Modules\Default\Entity\Menu;
use App\Modules\Default\Entity\MenuCategory;
use App\Modules\User\Entity\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouterInterface;

#[AsCommand(
    name: 'app:menu:seed-baseline',
    description: 'Seed the generic baseline admin menu'
)]
class SeedBaselineMenuCommand extends Command
{
    private const CATEGORIES = [
        'main' => ['name' => 'Main', 'label' => 'navigation.main', 'icon' => 'solar:home-bold', 'position' => 10],
        'administration' => ['name' => 'Administration', 'label' => 'navigation.administration', 'icon' => 'solar:settings-bold', 'position' => 20],
        'settings' => ['name' => 'Settings', 'label' => 'navigation.settings', 'icon' => 'solar:tuning-bold', 'position' => 30],
    ];

    private const MENU_DEFINITIONS = [
        ['route' => 'app_dashboard', 'name' => 'navigation.dashboard', 'category' => 'main', 'position' => 10, 'icon' => 'solar:home-bold', 'permission' => 'dashboard.view'],
        ['route' => 'user_list', 'name' => 'navigation.users', 'category' => 'administration', 'position' => 10, 'icon' => 'solar:users-group-rounded-bold', 'permission' => 'user.view'],
        ['route' => 'role_list', 'name' => 'navigation.roles', 'category' => 'administration', 'position' => 20, 'icon' => 'solar:shield-user-bold', 'permission' => 'role.view'],
        ['route' => 'permission_list', 'name' => 'navigation.permissions', 'category' => 'administration', 'position' => 30, 'icon' => 'solar:key-bold', 'permission' => 'permission.view'],
        ['route' => 'settings_list', 'name' => 'navigation.settings', 'category' => 'settings', 'position' => 10, 'icon' => 'solar:tuning-bold', 'permission' => 'settings.view'],
        ['route' => 'menu_index', 'name' => 'navigation.menu', 'category' => 'settings', 'position' => 20, 'icon' => 'solar:list-bold', 'permission' => 'menu.view'],
        ['route' => 'menu_category_index', 'name' => 'navigation.menu_categories', 'category' => 'settings', 'position' => 30, 'icon' => 'solar:folder-bold', 'permission' => 'menu_category.view'],
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
        $categoryRepository = $this->entityManager->getRepository(MenuCategory::class);
        $menuRepository = $this->entityManager->getRepository(Menu::class);
        $permissionRepository = $this->entityManager->getRepository(Permission::class);
        $categories = [];
        $warnings = [];

        foreach (self::CATEGORIES as $code => $definition) {
            $categories[$code] = $this->ensureCategory($categoryRepository, $code, $definition);
        }

        foreach (self::MENU_DEFINITIONS as $definition) {
            if ($this->router->getRouteCollection()->get($definition['route']) === null) {
                $warnings[] = sprintf('Route "%s" was not found.', $definition['route']);
                continue;
            }

            $menu = $menuRepository->findOneBy(['route' => $definition['route']]) ?? new Menu();
            $menu->setName($definition['name']);
            $menu->setRoute($definition['route']);
            $menu->setCategory($definition['category']);
            $menu->setMenuCategory($categories[$definition['category']] ?? null);
            $menu->setPosition($definition['position']);
            $menu->setIcon($definition['icon']);
            $menu->setParent(null);
            $menu->getPermissions()->clear();

            $permission = $this->findPermission($permissionRepository, $definition['permission']);
            if ($permission instanceof Permission) {
                $menu->addPermission($permission);
            } else {
                $warnings[] = sprintf('Permission "%s" was not found for route "%s".', $definition['permission'], $definition['route']);
            }

            $this->entityManager->persist($menu);
        }

        $this->entityManager->flush();

        foreach ($warnings as $warning) {
            $io->warning($warning);
        }

        $io->success('Generic baseline menu items were seeded or updated.');

        return Command::SUCCESS;
    }

    /**
     * @param array{name: string, label: string, icon: string, position: int} $definition
     */
    private function ensureCategory(ObjectRepository $categoryRepository, string $code, array $definition): MenuCategory
    {
        $category = $categoryRepository->findOneBy(['code' => $code]) ?? new MenuCategory();
        $category->setCode($code);
        $category->setName($definition['name']);
        $category->setLabelKey($definition['label']);
        $category->setIcon($definition['icon']);
        $category->setPosition($definition['position']);
        $category->setActive(true);
        $this->entityManager->persist($category);

        return $category;
    }

    private function findPermission(ObjectRepository $permissionRepository, string $name): ?Permission
    {
        $permission = $permissionRepository->findOneBy(['name' => $name]);
        if ($permission instanceof Permission) {
            return $permission;
        }

        return $permissionRepository->findOneBy(['route' => preg_replace('/\.(view|create|update|delete)$/', '', $name)]);
    }
}
