<?php

namespace App\Modules\User\Repository;

use App\Modules\User\Entity\Permission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Permission>
 *
 * @method Permission|null find($id, $lockMode = null, $lockVersion = null)
 * @method Permission|null findOneBy(array $criteria, array $orderBy = null)
 * @method Permission[]    findAll()
 * @method Permission[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PermissionRepository extends ServiceEntityRepository
{
    private const GROUP_ORDER = [
        'permission' => 10,
        'role' => 20,
        'menu' => 30,
        'menu_category' => 40,
        'settings' => 50,
        'user' => 60,
        'dashboard' => 70,
        'other' => 999,
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
    }

    /**
     * Returns permissions grouped for the admin index using the active Permission model.
     *
     * @return array<string, Permission[]>
     */
    public function findGroupedForAdmin(): array
    {
        $permissions = $this->createQueryBuilder('p')
            ->leftJoin('p.controllerActions', 'ca')
            ->addSelect('ca')
            ->orderBy('p.route', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();

        $groups = [];

        foreach ($permissions as $permission) {
            $group = $this->resolveGroup($permission);
            $groups[$group][] = $permission;
        }

        uksort($groups, static function (string $left, string $right): int {
            $leftOrder = self::GROUP_ORDER[$left] ?? 500;
            $rightOrder = self::GROUP_ORDER[$right] ?? 500;

            if ($leftOrder === $rightOrder) {
                return $left <=> $right;
            }

            return $leftOrder <=> $rightOrder;
        });

        foreach ($groups as &$groupPermissions) {
            usort($groupPermissions, static function (Permission $left, Permission $right): int {
                return [
                    (string) $left->getRoute(),
                    (string) $left->getName(),
                    $left->getId() ?? 0,
                ] <=> [
                    (string) $right->getRoute(),
                    (string) $right->getName(),
                    $right->getId() ?? 0,
                ];
            });
        }
        unset($groupPermissions);

        return $groups;
    }

    /**
     * @return array<string, Permission[]>
     */
    public function findGroupedForRoleForm(): array
    {
        $groups = [];

        foreach ($this->findOrderedWithControllerActions() as $permission) {
            $groups[$this->resolveGroup($permission)][] = $permission;
        }

        $groupOrder = [
            'permission' => 10,
            'role' => 20,
            'menu' => 30,
            'menu_category' => 40,
            'settings' => 50,
            'user' => 60,
            'dashboard' => 70,
            'other' => 999,
        ];

        uksort($groups, static function (string $left, string $right) use ($groupOrder): int {
            $leftOrder = $groupOrder[$left] ?? 500;
            $rightOrder = $groupOrder[$right] ?? 500;

            if ($leftOrder === $rightOrder) {
                return $left <=> $right;
            }

            return $leftOrder <=> $rightOrder;
        });

        foreach ($groups as &$groupPermissions) {
            $this->sortPermissions($groupPermissions);
        }
        unset($groupPermissions);

        return $groups;
    }

    /**
     * @return Permission[]
     */
    private function findOrderedWithControllerActions(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.controllerActions', 'ca')
            ->addSelect('ca')
            ->orderBy('p.route', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Permission[] $permissions
     */
    private function sortPermissions(array &$permissions): void
    {
        usort($permissions, static function (Permission $left, Permission $right): int {
            return [
                (string) $left->getRoute(),
                (string) $left->getName(),
                $left->getId() ?? 0,
            ] <=> [
                (string) $right->getRoute(),
                (string) $right->getName(),
                $right->getId() ?? 0,
            ];
        });
    }

    private function resolveGroup(Permission $permission): string
    {
        $route = trim((string) $permission->getRoute());
        if ($route !== '') {
            return $this->normalizeGroup((string) preg_replace('/_.*$/', '', $route));
        }

        foreach ($permission->getControllerActions() as $controllerAction) {
            if (!method_exists($controllerAction, 'getController')) {
                continue;
            }

            $controller = (string) $controllerAction->getController();
            if ($controller === '') {
                continue;
            }

            $parts = explode('\\', $controller);
            $basename = preg_replace('/Controller$/', '', (string) end($parts));
            if ($basename === '') {
                continue;
            }

            return $this->normalizeGroup((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $basename));
        }

        return 'other';
    }

    private function normalizeGroup(string $group): string
    {
        $group = strtolower(trim($group));
        $group = preg_replace('/[^a-z0-9_]+/', '_', $group) ?: '';
        $group = trim($group, '_');

        return $group !== '' ? $group : 'other';
    }

//    /**
//     * @return Role[] Returns an array of Role objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Role
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }


}
