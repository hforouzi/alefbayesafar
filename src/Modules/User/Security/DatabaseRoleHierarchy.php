<?php
namespace App\Modules\User\Security;

use App\Modules\User\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;

class DatabaseRoleHierarchy implements RoleHierarchyInterface
{
    private EntityManagerInterface $entityManager;
    private CacheInterface $cache;
    private LoggerInterface $logger;
    private array $roleHierarchy = [];
    private const CACHE_KEY = 'user_role_hierarchy';
    private const CACHE_TTL = 3600; // 1 hour
    
    public function __construct(
        EntityManagerInterface $entityManager,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->cache = $cache;
        $this->logger = $logger;
    }
    
    private function buildHierarchy(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL);
            
            $this->logger->info('Loading user role hierarchy from database');
            
            $hierarchy = [];
            $roles = $this->entityManager->getRepository(Role::class)
                ->findAll();

            foreach ($roles as $role) {
                $roleName = $role->getName();
                $parent = $role->getParent();
                $parentName = $parent?->getName();

                if ($roleName === null || $parentName === null) {
                    continue;
                }

                $hierarchy[$parentName][] = $roleName;
            }
            
            foreach ($hierarchy as $parentName => $childNames) {
                $hierarchy[$parentName] = array_values(array_unique($childNames));
            }

            $this->logger->debug('Role hierarchy built', ['hierarchy' => $hierarchy]);
            
            return $hierarchy;
        });
    }
    
    public function getReachableRoleNames(array $roles): array
    {
        if (empty($this->roleHierarchy)) {
            $this->roleHierarchy = $this->buildHierarchy();
        }
        
        $reachableRoles = [];
        foreach ($roles as $role) {
            $this->collectReachableRoleNames($role, $reachableRoles, []);
        }

        return array_keys($reachableRoles);
    }

    /**
     * @return array<string, string[]>
     */
    public function getRoleHierarchy(): array
    {
        if (empty($this->roleHierarchy)) {
            $this->roleHierarchy = $this->buildHierarchy();
        }

        return $this->roleHierarchy;
    }

    /**
     * @param array<string, true> $reachableRoles
     * @param array<string, true> $visitedRoles
     */
    private function collectReachableRoleNames(string $role, array &$reachableRoles, array $visitedRoles): void
    {
        $reachableRoles[$role] = true;

        if (isset($visitedRoles[$role])) {
            return;
        }

        $visitedRoles[$role] = true;
        foreach ($this->roleHierarchy[$role] ?? [] as $childRole) {
            $this->collectReachableRoleNames($childRole, $reachableRoles, $visitedRoles);
        }
    }
    
    public function invalidateCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);
        $this->roleHierarchy = [];
        $this->logger->info('User role hierarchy cache invalidated');
    }
    
    public function warmUpCache(): void
    {
        $this->roleHierarchy = $this->buildHierarchy();
        $this->logger->info('User role hierarchy cache warmed up');
    }
}
