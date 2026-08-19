<?php
namespace App\Modules\User\Service;

use App\Modules\User\Entity\Permission;
use App\Modules\User\Entity\Role;
use App\Modules\User\Security\DatabaseRoleHierarchy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class PermissionChecker
{
    private const ROLE_ALIASES = [
        'SUPER_ADMIN' => 'ROLE_SUPER_ADMIN',
        'ADMIN' => 'ROLE_ADMIN',
        'USER' => 'ROLE_USER',
        'admin' => 'ROLE_ADMIN',
        'user' => 'ROLE_USER',
    ];

    private EntityManagerInterface $entityManager;
    private Security $security;
    private DatabaseRoleHierarchy $databaseRoleHierarchy;
    
    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        DatabaseRoleHierarchy $databaseRoleHierarchy
    )
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->databaseRoleHierarchy = $databaseRoleHierarchy;
    }
    
    /**
     * Checks if the current user has permission for a specific controller action.
     *
     * @param string $controller
     * @param string $action
     * @return bool
     */
    public function hasPermission(string $controller, string $action): bool
    {
        if ($action == "login" || $action == "captcha") {
            return true;
        }
        
        $user = $this->security->getUser();
        if (!$user) {
            return false;
        }
        
        $userRoles = $this->getExpandedUserRoles($user->getRoles());

        if (in_array('ROLE_SUPER_ADMIN', $userRoles, true)) {
            return true;
        }
        
        foreach ($userRoles as $roleName) {
            $roleEntity = $this->getUserPermissionsByRole($roleName);
            
            if ($roleEntity === null) {
                continue;
            }
            
            $permissions = $roleEntity->getPermissions();
            if ($permissions === null || $permissions->isEmpty()) {
                continue;
            }
            
            foreach ($permissions as $permission) {
                if (!method_exists($permission, 'getControllerActions')) {
                    continue;
                }
                
                $controllerActions = $permission->getControllerActions();
                if ($controllerActions === null) {
                    continue;
                }
                
                foreach ($controllerActions as $controllerAction) {
                    if ($controllerAction->getController() === $controller &&
                        $controllerAction->getAction() === $action) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Retrieves role entity by role name.
     *
     * @param string $roleName
     * @return Role|null
     */
    private function getUserPermissionsByRole(string $roleName): ?Role
    {
        try {
            return $this->entityManager->getRepository(Role::class)
                ->findOneBy(['name' => $roleName]);
        } catch (\Exception $e) {
            // Log error if needed
            return null;
        }
    }
    
    /**
     * Legacy compatibility method.
     *
     * @deprecated Use getUserPermissionsByRole instead.
     */
    private function getUserPermissions($userRoles)
    {
        if (is_array($userRoles) && !empty($userRoles)) {
            return $this->getUserPermissionsByRole($userRoles[0]);
        }
        return null;
    }
    
    public function isMenuAccess($MenuPermissions): bool
    {
        $user = $this->security->getUser();
        if (!$user) {
            return false;
        }
        
        $userRoles = $this->getExpandedUserRoles($user->getRoles());

        if (in_array('ROLE_SUPER_ADMIN', $userRoles, true)) {
            return true;
        }

        $requiredPermissions = $this->normalizePermissions($MenuPermissions);
        if ($requiredPermissions === []) {
            return true;
        }

        $allowedPermissionKeys = $this->collectAllowedPermissionKeys($userRoles);

        foreach ($requiredPermissions as $permission) {
            $permissionKey = $this->buildPermissionKey($permission);
            if ($permissionKey !== null && in_array($permissionKey, $allowedPermissionKeys, true)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * @param string[] $directRoles
     * @return string[]
     */
    private function normalizeRoles(array $directRoles): array
    {
        $normalizedRoles = [];

        foreach ($directRoles as $role) {
            if (!is_string($role) || trim($role) === '') {
                continue;
            }

            $role = strtoupper(trim($role));
            if (str_starts_with($role, 'ROLE_')) {
                $normalizedRoles[] = $role;
                continue;
            }

            $aliasKey = $role;
            $normalizedRoles[] = self::ROLE_ALIASES[$role] ?? self::ROLE_ALIASES[$aliasKey] ?? 'ROLE_' . $aliasKey;
        }

        return array_values(array_unique($normalizedRoles));
    }

    /**
     * @param string[] $directRoles
     * @return string[]
     */
    private function getExpandedUserRoles(array $directRoles): array
    {
        $normalizedRoles = $this->normalizeRoles($directRoles);

        try {
            $expandedRoles = $this->databaseRoleHierarchy->getReachableRoleNames($normalizedRoles);
        } catch (\Throwable) {
            $expandedRoles = $normalizedRoles;
        }

        return $this->normalizeRoles($expandedRoles);
    }

    /**
     * @param iterable<mixed> $permissions
     * @return array<int, mixed>
     */
    private function normalizePermissions(mixed $permissions): array
    {
        if ($permissions === null) {
            return [];
        }

        if (is_array($permissions)) {
            return array_values(array_filter($permissions, static fn ($permission): bool => $permission !== null));
        }

        if ($permissions instanceof \Traversable) {
            return iterator_to_array($permissions, false);
        }

        return [];
    }

    /**
     * @param string[] $roleNames
     * @return string[]
     */
    private function collectAllowedPermissionKeys(array $roleNames): array
    {
        $allowedPermissionKeys = [];

        foreach ($roleNames as $roleName) {
            $roleEntity = $this->getUserPermissionsByRole($roleName);
            if ($roleEntity === null) {
                continue;
            }

            $permissions = $roleEntity->getPermissions();
            if ($permissions === null || $permissions->isEmpty()) {
                continue;
            }

            foreach ($permissions as $permission) {
                $permissionKey = $this->buildPermissionKey($permission);
                if ($permissionKey !== null) {
                    $allowedPermissionKeys[$permissionKey] = true;
                }
            }
        }

        return array_keys($allowedPermissionKeys);
    }

    private function buildPermissionKey(mixed $permission): ?string
    {
        if (!$permission instanceof Permission) {
            if (is_object($permission) && method_exists($permission, 'getId') && method_exists($permission, 'getName')) {
                $permissionId = $permission->getId();
                if ($permissionId !== null) {
                    return 'id:' . $permissionId;
                }
                $name = trim((string) $permission->getName());
                $route = method_exists($permission, 'getRoute') ? trim((string) $permission->getRoute()) : '';
                if ($name !== '' || $route !== '') {
                    return 'legacy:' . mb_strtolower($name . '|' . $route);
                }
            }

            return null;
        }

        if ($permission->getId() !== null) {
            return 'id:' . $permission->getId();
        }

        $name = trim((string) $permission->getName());
        $route = trim((string) $permission->getRoute());

        if ($name === '' && $route === '') {
            return null;
        }

        return 'legacy:' . mb_strtolower($name . '|' . $route);
    }
}
