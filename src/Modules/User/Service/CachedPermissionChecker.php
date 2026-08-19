<?php
namespace App\Modules\User\Service;

use App\Modules\User\Entity\Permission;
use App\Modules\User\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;

class CachedPermissionChecker
{
    private EntityManagerInterface $entityManager;
    private Security $security;
    private CacheInterface $cache;
    private LoggerInterface $logger;
    
    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->cache = $cache;
        $this->logger = $logger;
    }
    
    /**
     * Checks if the current user has permission for a specific controller action.
     */
    public function hasPermission(string $controller, string $action): bool
    {
        // Public actions
        if ($action == "login" || $action == "captcha") {
            return true;
        }
        
        $user = $this->security->getUser();
        if (!$user) {
            return false;
        }
        
        // Admin override
        if (in_array("admin", $user->getRoles())) {
            return true;
        }
        
        $userRoles = $user->getRoles();
        $cacheKey = sprintf('user_permission_%s_%s_%s',
            md5(implode('|', $userRoles)),
            str_replace('\\', '_', $controller),
            $action
        );
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($controller, $action, $userRoles) {
            $item->expiresAfter(1800); // 30 minutes
            
            $this->logger->debug('Checking permission from database', [
                'controller' => $controller,
                'action' => $action,
                'roles' => $userRoles
            ]);
            
            return $this->checkPermissionFromDatabase($controller, $action, $userRoles);
        });
    }
    
    private function checkPermissionFromDatabase(string $controller, string $action, array $userRoles): bool
    {
        foreach ($userRoles as $roleName) {
            $userPermissions = $this->getUserPermissions($roleName);
            
            if ($userPermissions) {
                foreach ($userPermissions->getPermissions() as $permission) {
                    foreach ($permission->getControllerActions() as $controllerAction) {
                        if ($controllerAction->getController() === $controller &&
                            $controllerAction->getAction() === $action) {
                            return true;
                        }
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Retrieves permissions associated with a user role.
     */
    private function getUserPermissions(string $roleName): ?Role
    {
        return $this->entityManager->getRepository(Role::class)->findOneBy(['name' => $roleName]);
    }
    
    public function isMenuAccess($MenuPermissions): bool
    {
        $user = $this->security->getUser();
        if (!$user) {
            return false;
        }
        
        if (in_array("admin", $user->getRoles())) {
            return true;
        }
        
        $userRoles = $user->getRoles();
        $cacheKey = sprintf('menu_access_%s_%s',
            md5(implode('|', $userRoles)),
            md5(serialize($MenuPermissions))
        );
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($MenuPermissions, $userRoles) {
            $item->expiresAfter(1800); // 30 minutes
            
            return $this->checkMenuAccessFromDatabase($MenuPermissions, $userRoles);
        });
    }
    
    private function checkMenuAccessFromDatabase($MenuPermissions, array $userRoles): bool
    {
        foreach ($MenuPermissions as $permission) {
            $userPermission = $this->entityManager->getRepository(Role::class)
                ->findBy(['name' => $userRoles]);
            
            foreach ($userPermission as $item) {
                foreach ($item->getPermissions() as $val) {
                    if ($permission === $val) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    public function invalidateUserCache(?string $userIdentifier = null): void
    {
        if ($userIdentifier) {
            // پاک کردن cache های مربوط به user خاص
            $pattern = sprintf('user_permission_%s_*', md5($userIdentifier));
            // Implementation depends on your cache adapter
        } else {
            // پاک کردن کل cache های permission
            $this->cache->clear();
        }
        
        $this->logger->info('Permission cache invalidated', ['user' => $userIdentifier]);
    }
}