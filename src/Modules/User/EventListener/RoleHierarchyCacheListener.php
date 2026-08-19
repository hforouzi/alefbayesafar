<?php
namespace App\Modules\User\EventListener;

use App\Modules\User\Entity\Role;
use App\Modules\User\Security\DatabaseRoleHierarchy;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class RoleHierarchyCacheListener
{
    private DatabaseRoleHierarchy $roleHierarchy;
    private LoggerInterface $logger;
    
    public function __construct(
        DatabaseRoleHierarchy $roleHierarchy,
        LoggerInterface $logger
    ) {
        $this->roleHierarchy = $roleHierarchy;
        $this->logger = $logger;
    }
    
    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->invalidateCacheIfRoleEntity($args, 'created');
    }
    
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->invalidateCacheIfRoleEntity($args, 'updated');
    }
    
    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->invalidateCacheIfRoleEntity($args, 'deleted');
    }
    
    private function invalidateCacheIfRoleEntity(LifecycleEventArgs $args, string $operation): void
    {
        $entity = $args->getObject();
        
        if ($entity instanceof Role) {
            $this->logger->info('Role hierarchy cache invalidated', [
                'operation' => $operation,
                'role_id' => $entity->getId() ?? 'unknown',
                'role_name' => $entity->getName() ?? 'unknown'
            ]);
            
            $this->roleHierarchy->invalidateCache();
        }
    }
}