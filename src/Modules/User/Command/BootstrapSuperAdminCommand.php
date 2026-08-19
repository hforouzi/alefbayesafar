<?php

namespace App\Modules\User\Command;

use App\Modules\Default\Controller\DefaultController;
use App\Modules\Default\Controller\MenuCategoryController;
use App\Modules\Default\Controller\MenuController;
use App\Modules\Default\Controller\SettingController;
use App\Modules\User\Controller\PermissionController;
use App\Modules\User\Controller\RoleController;
use App\Modules\User\Controller\UserController;
use App\Modules\User\Entity\ControllerAction;
use App\Modules\User\Entity\Permission;
use App\Modules\User\Entity\Role;
use App\Modules\User\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:security:bootstrap-super-admin',
    description: 'Bootstrap generic roles, permissions, and an optional first Super Admin user'
)]
class BootstrapSuperAdminCommand extends Command
{
    /**
     * @var array<string, array{label: string, route: string, controller: class-string, action: string}>
     */
    private const PERMISSIONS = [
        'dashboard.view' => ['label' => 'dashboard.view', 'route' => 'app_dashboard', 'controller' => DefaultController::class, 'action' => 'dashboard'],
        'user.view' => ['label' => 'user.view', 'route' => 'user_list', 'controller' => UserController::class, 'action' => 'listUsers'],
        'user.create' => ['label' => 'user.create', 'route' => 'user_new', 'controller' => UserController::class, 'action' => 'newUser'],
        'user.update' => ['label' => 'user.update', 'route' => 'user_edit', 'controller' => UserController::class, 'action' => 'editUser'],
        'user.delete' => ['label' => 'user.delete', 'route' => 'user_delete', 'controller' => UserController::class, 'action' => 'deleteUser'],
        'role.view' => ['label' => 'role.view', 'route' => 'role_list', 'controller' => RoleController::class, 'action' => 'list'],
        'role.create' => ['label' => 'role.create', 'route' => 'role_new', 'controller' => RoleController::class, 'action' => 'new'],
        'role.update' => ['label' => 'role.update', 'route' => 'role_edit', 'controller' => RoleController::class, 'action' => 'edit'],
        'role.delete' => ['label' => 'role.delete', 'route' => 'role_delete', 'controller' => RoleController::class, 'action' => 'delete'],
        'permission.view' => ['label' => 'permission.view', 'route' => 'permission_list', 'controller' => PermissionController::class, 'action' => 'list'],
        'permission.create' => ['label' => 'permission.create', 'route' => 'permission_new', 'controller' => PermissionController::class, 'action' => 'new'],
        'permission.update' => ['label' => 'permission.update', 'route' => 'permission_edit', 'controller' => PermissionController::class, 'action' => 'edit'],
        'permission.delete' => ['label' => 'permission.delete', 'route' => 'permission_delete', 'controller' => PermissionController::class, 'action' => 'delete'],
        'menu.view' => ['label' => 'menu.view', 'route' => 'menu_index', 'controller' => MenuController::class, 'action' => 'index'],
        'menu.create' => ['label' => 'menu.create', 'route' => 'menu_new', 'controller' => MenuController::class, 'action' => 'new'],
        'menu.update' => ['label' => 'menu.update', 'route' => 'menu_edit', 'controller' => MenuController::class, 'action' => 'edit'],
        'menu.delete' => ['label' => 'menu.delete', 'route' => 'menu_delete', 'controller' => MenuController::class, 'action' => 'delete'],
        'menu_category.view' => ['label' => 'menu_category.view', 'route' => 'menu_category_index', 'controller' => MenuCategoryController::class, 'action' => 'index'],
        'menu_category.create' => ['label' => 'menu_category.create', 'route' => 'menu_category_new', 'controller' => MenuCategoryController::class, 'action' => 'new'],
        'menu_category.update' => ['label' => 'menu_category.update', 'route' => 'menu_category_edit', 'controller' => MenuCategoryController::class, 'action' => 'edit'],
        'menu_category.delete' => ['label' => 'menu_category.delete', 'route' => 'menu_category_delete', 'controller' => MenuCategoryController::class, 'action' => 'delete'],
        'settings.view' => ['label' => 'settings.view', 'route' => 'settings_list', 'controller' => SettingController::class, 'action' => 'list'],
        'settings.update' => ['label' => 'settings.update', 'route' => 'edit_setting', 'controller' => SettingController::class, 'action' => 'edit'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Create or promote a user by email')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password to use when creating the user')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without flushing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $email = trim((string) $input->getOption('email'));
        $password = (string) $input->getOption('password');

        $roleRepository = $this->entityManager->getRepository(Role::class);
        $permissionRepository = $this->entityManager->getRepository(Permission::class);
        $controllerActionRepository = $this->entityManager->getRepository(ControllerAction::class);

        $superAdmin = $this->ensureRole($roleRepository, 'ROLE_SUPER_ADMIN');
        $admin = $this->ensureRole($roleRepository, 'ROLE_ADMIN');
        $userRole = $this->ensureRole($roleRepository, 'ROLE_USER');
        $admin->setParent($superAdmin);
        $userRole->setParent($admin);

        foreach (self::PERMISSIONS as $definition) {
            $permission = $this->findPermission($permissionRepository, $definition['route'], $definition['label']);
            $permission ??= new Permission();
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

            if (!$superAdmin->getPermissions()->contains($permission)) {
                $superAdmin->addPermission($permission);
            }
        }

        if ($email !== '') {
            if ($password === '' && $input->isInteractive()) {
                $password = (string) $io->askHidden('Super Admin password', static function (?string $value): string {
                    $password = trim((string) $value);
                    if ($password === '') {
                        throw new \InvalidArgumentException('A password is required when creating a new Super Admin user.');
                    }

                    return $password;
                });
            }

            $this->createOrPromoteSuperAdmin($email, $password, $superAdmin);
        }

        if ($dryRun) {
            $io->success('Dry-run completed. No changes were flushed.');
            return Command::SUCCESS;
        }

        $this->entityManager->flush();
        $io->success('Generic roles, permissions, and Super Admin access were bootstrapped.');

        return Command::SUCCESS;
    }

    private function ensureRole(ObjectRepository $roleRepository, string $roleName): Role
    {
        $role = $roleRepository->findOneBy(['name' => $roleName]);
        if ($role instanceof Role) {
            return $role;
        }

        $role = new Role();
        $role->setName($roleName);
        $this->entityManager->persist($role);

        return $role;
    }

    private function findPermission(ObjectRepository $permissionRepository, string $route, string $label): ?Permission
    {
        $permission = $permissionRepository->findOneBy(['route' => $route]);
        if ($permission instanceof Permission) {
            return $permission;
        }

        return $permissionRepository->findOneBy(['name' => $label]);
    }

    private function createOrPromoteSuperAdmin(string $email, string $password, Role $superAdmin): void
    {
        $userRepository = $this->entityManager->getRepository(UserEntity::class);
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user instanceof UserEntity) {
            if ($password === '') {
                throw new \InvalidArgumentException('A password is required when creating a new Super Admin user.');
            }

            $user = new UserEntity();
            $user->setEmail($email);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $this->entityManager->persist($user);
        }

        if (!$user->getUserRoles()->contains($superAdmin)) {
            $user->addUserRole($superAdmin);
        }
    }
}
