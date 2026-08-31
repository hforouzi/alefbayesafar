<?php

namespace App\Modules\Default\Service;

use App\Modules\Default\Bootstrap\ApplicationBootstrapData;
use App\Modules\Default\Bootstrap\ApplicationBootstrapResult;
use App\Modules\Default\Entity\AppSetting;
use App\Modules\Default\Entity\Menu;
use App\Modules\Default\Entity\MenuCategory;
use App\Modules\User\Entity\ControllerAction;
use App\Modules\User\Entity\Permission;
use App\Modules\User\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;

final readonly class ApplicationBootstrapService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RouterInterface $router,
        private ApplicationBootstrapData $data,
    ) {
    }

    public function bootstrap(bool $dryRun = false): ApplicationBootstrapResult
    {
        $result = new ApplicationBootstrapResult();

        $this->entityManager->wrapInTransaction(function () use ($dryRun, $result): void {
            $roles = $this->roles($dryRun, $result);
            $permissions = $this->permissions($dryRun, $result);
            $this->assignSuperAdminPermissions($roles['ROLE_SUPER_ADMIN'] ?? null, $permissions, $dryRun, $result);
            $categories = $this->menuCategories($dryRun, $result);
            $this->menus($categories, $permissions, $dryRun, $result);
            $this->settings($dryRun, $result);

            if ($dryRun) {
                $this->entityManager->clear();
            }
        });

        return $result;
    }

    /**
     * @return array<string, Role>
     */
    private function roles(bool $dryRun, ApplicationBootstrapResult $result): array
    {
        $repository = $this->entityManager->getRepository(Role::class);
        $roles = [];

        foreach ($this->data->roles() as $roleName) {
            $role = $repository->findOneBy(['name' => $roleName]);
            if ($role instanceof Role) {
                $result->record('Roles', 'existing');
            } else {
                $role = (new Role())->setName($roleName);
                $result->record('Roles', 'created');
                if (!$dryRun) {
                    $this->entityManager->persist($role);
                }
            }
            $roles[$roleName] = $role;
        }

        foreach ($this->data->roleParents() as $roleName => $parentName) {
            $role = $roles[$roleName] ?? null;
            $parent = $parentName !== null ? ($roles[$parentName] ?? null) : null;
            if (!$role instanceof Role) {
                continue;
            }

            if ($role->getParent()?->getName() === $parentName || ($parentName === null && $role->getParent() === null)) {
                $result->record('Role hierarchy', 'existing');
                continue;
            }

            $result->record('Role hierarchy', 'updated');
            if (!$dryRun) {
                $role->setParent($parent);
            }
        }

        return $roles;
    }

    /**
     * @return array<string, Permission>
     */
    private function permissions(bool $dryRun, ApplicationBootstrapResult $result): array
    {
        $permissionRepository = $this->entityManager->getRepository(Permission::class);
        $controllerActionRepository = $this->entityManager->getRepository(ControllerAction::class);
        $permissions = [];

        foreach ($this->data->permissions() as $key => $definition) {
            if ($this->router->getRouteCollection()->get($definition['route']) === null) {
                $result->record('Permissions', 'skipped');
                $result->warning(sprintf('Skipped permission "%s": route "%s" was not found.', $key, $definition['route']));
                continue;
            }

            $permission = $permissionRepository->findOneBy(['route' => $definition['route']]);
            if (!$permission instanceof Permission) {
                $permission = $permissionRepository->findOneBy(['name' => $definition['label']]);
            }

            if ($permission instanceof Permission) {
                $changed = $permission->getName() !== $definition['label'] || $permission->getRoute() !== $definition['route'];
                $result->record('Permissions', $changed ? 'updated' : 'existing');
            } else {
                $permission = new Permission();
                $changed = true;
                $result->record('Permissions', 'created');
            }

            $controllerAction = $controllerActionRepository->findOneBy([
                'controller' => $definition['controller'],
                'action' => $definition['action'],
            ]);

            if (!$controllerAction instanceof ControllerAction) {
                $controllerAction = (new ControllerAction())
                    ->setController($definition['controller'])
                    ->setAction($definition['action']);
                $result->record('Controller actions', 'created');
                if (!$dryRun) {
                    $this->entityManager->persist($controllerAction);
                }
                $changed = true;
            } else {
                $result->record('Controller actions', 'existing');
            }

            if (!$permission->getControllerActions()->contains($controllerAction)) {
                $changed = true;
            }

            if (!$dryRun) {
                $permission->setName($definition['label']);
                $permission->setRoute($definition['route']);
                if (!$permission->getControllerActions()->contains($controllerAction)) {
                    $permission->addControllerAction($controllerAction);
                }
                $this->entityManager->persist($permission);
            }

            $permissions[$key] = $permission;
        }

        return $permissions;
    }

    /**
     * @param array<string, Permission> $permissions
     */
    private function assignSuperAdminPermissions(?Role $superAdmin, array $permissions, bool $dryRun, ApplicationBootstrapResult $result): void
    {
        if (!$superAdmin instanceof Role) {
            return;
        }

        foreach ($permissions as $permission) {
            if ($superAdmin->getPermissions()->contains($permission)) {
                $result->record('Super Admin permissions', 'existing');
                continue;
            }

            $result->record('Super Admin permissions', 'updated');
            if (!$dryRun) {
                $superAdmin->addPermission($permission);
            }
        }
    }

    /**
     * @return array<string, MenuCategory>
     */
    private function menuCategories(bool $dryRun, ApplicationBootstrapResult $result): array
    {
        $repository = $this->entityManager->getRepository(MenuCategory::class);
        $categories = [];

        foreach ($this->data->menuCategories() as $code => $definition) {
            $category = $repository->findOneBy(['code' => $code]);
            if ($category instanceof MenuCategory) {
                $changed = $category->getName() !== $definition['name']
                    || $category->getLabelKey() !== $definition['label']
                    || $category->getIcon() !== $definition['icon']
                    || $category->getPosition() !== $definition['position']
                    || $category->isActive() !== $definition['active'];
                $result->record('Menu categories', $changed ? 'updated' : 'existing');
            } else {
                $category = new MenuCategory();
                $changed = true;
                $result->record('Menu categories', 'created');
            }

            if (!$dryRun) {
                $category->setCode($code);
                $category->setName($definition['name']);
                $category->setLabelKey($definition['label']);
                $category->setIcon($definition['icon']);
                $category->setPosition($definition['position']);
                $category->setActive($definition['active']);
                $this->entityManager->persist($category);
            }

            $categories[$code] = $category;
        }

        return $categories;
    }

    /**
     * @param array<string, MenuCategory> $categories
     * @param array<string, Permission>   $permissions
     */
    private function menus(array $categories, array $permissions, bool $dryRun, ApplicationBootstrapResult $result): void
    {
        $repository = $this->entityManager->getRepository(Menu::class);

        foreach ($this->data->menus() as $definition) {
            if ($this->router->getRouteCollection()->get($definition['route']) === null) {
                $result->record('Menus', 'skipped');
                $result->warning(sprintf('Skipped menu "%s": route "%s" was not found.', $definition['name'], $definition['route']));
                continue;
            }

            $category = $categories[$definition['category']] ?? null;
            $permission = $permissions[$definition['permission']] ?? null;
            $menu = $repository->findOneBy(['route' => $definition['route']]);

            if ($menu instanceof Menu) {
                $changed = $menu->getName() !== $definition['name']
                    || $menu->getCategory() !== $definition['category']
                    || $menu->getIcon() !== $definition['icon']
                    || $menu->getPosition() !== $definition['position']
                    || $menu->getParent() !== null
                    || $menu->getMenuCategory()?->getCode() !== $definition['category']
                    || !$permission instanceof Permission
                    || !$menu->getPermissions()->contains($permission)
                    || $menu->getPermissions()->count() !== 1;
                $result->record('Menus', $changed ? 'updated' : 'existing');
            } else {
                $menu = new Menu();
                $changed = true;
                $result->record('Menus', 'created');
            }

            if (!$dryRun) {
                $menu->setName($definition['name']);
                $menu->setRoute($definition['route']);
                $menu->setCategory($definition['category']);
                $menu->setMenuCategory($category);
                $menu->setIcon($definition['icon']);
                $menu->setPosition($definition['position']);
                $menu->setParent(null);
                $menu->getPermissions()->clear();
                if ($permission instanceof Permission) {
                    $menu->addPermission($permission);
                }
                $this->entityManager->persist($menu);
            }
        }
    }

    private function settings(bool $dryRun, ApplicationBootstrapResult $result): void
    {
        $repository = $this->entityManager->getRepository(AppSetting::class);

        foreach ($this->data->settings() as $definition) {
            $setting = $repository->findOneBy(['name' => $definition['name']]);
            if ($setting instanceof AppSetting) {
                $result->record('Settings', 'existing');
                continue;
            }

            $result->record('Settings', 'created');
            if (!$dryRun) {
                $setting = (new AppSetting())
                    ->setName($definition['name'])
                    ->setValue($definition['value']);
                $this->entityManager->persist($setting);
            }
        }
    }
}
