<?php

namespace App\Modules\User\Repository;

use App\Modules\User\Entity\UserEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<UserEntity>
 * @implements PasswordUpgraderInterface<UserEntity>
 *
 * @method UserEntity|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserEntity|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserEntity[]    findAll()
 * @method UserEntity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserEntityRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserEntity::class);
    }
    
    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof UserEntity) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }
        
        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
    
    /**
     * پیدا کردن کاربران بر اساس نام Role (از جدول Role Entity)
     *
     * @param string $roleName نام role مثل 'ROLE_ADMIN', 'admin', 'editor'
     * @return UserEntity[]
     */
    public function findByRoleName(string $roleName): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.userRoles', 'r')
            ->where('r.name = :roleName')
            ->setParameter('roleName', $roleName)
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * پیدا کردن کاربران بر اساس چندین Role
     *
     * @param array $roleNames آرایه‌ای از نام role ها مثل ['ROLE_MANAGER', 'ROLE_ADMIN']
     * @return UserEntity[]
     */
    public function findByRoleNames(array $roleNames): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.userRoles', 'r')
            ->where('r.name IN (:roleNames)')
            ->setParameter('roleNames', $roleNames)
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * پیدا کردن کاربران فعال بر اساس Role
     *
     * @param string $roleName
     * @return UserEntity[]
     */
    public function findActiveUsersByRole(string $roleName): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.userRoles', 'r')
            ->where('r.name = :roleName')
            ->andWhere('u.isActive = :active')
            ->setParameter('roleName', $roleName)
            ->setParameter('active', true)
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * تعداد کاربران یک Role خاص
     *
     * @param string $roleName
     * @return int
     */
    public function countUsersByRole(string $roleName): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->innerJoin('u.userRoles', 'r')
            ->where('r.name = :roleName')
            ->setParameter('roleName', $roleName)
            ->getQuery()
            ->getSingleScalarResult();
    }
    
    /**
     * پیدا کردن کاربران که حداقل یکی از Role های مشخص شده را دارند
     *
     * @param array $roleNames
     * @return UserEntity[]
     */
    public function findUsersWithAnyRole(array $roleNames): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.userRoles', 'r')
            ->where('r.name IN (:roleNames)')
            ->setParameter('roleNames', $roleNames)
            ->groupBy('u.id') // برای جلوگیری از duplicate
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * پیدا کردن کاربران که همه Role های مشخص شده را دارند
     *
     * @param array $roleNames
     * @return UserEntity[]
     */
    public function findUsersWithAllRoles(array $roleNames): array
    {
        $qb = $this->createQueryBuilder('u');
        
        foreach ($roleNames as $index => $roleName) {
            $qb->innerJoin('u.userRoles', "r{$index}")
                ->andWhere("r{$index}.name = :roleName{$index}")
                ->setParameter("roleName{$index}", $roleName);
        }
        
        return $qb->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * جستجوی کاربران بر اساس email و Role
     *
     * @param string $email
     * @param string $roleName
     * @return UserEntity[]
     */
    public function findByEmailAndRole(string $email, string $roleName): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.userRoles', 'r')
            ->where('u.email LIKE :email')
            ->andWhere('r.name = :roleName')
            ->setParameter('email', '%' . $email . '%')
            ->setParameter('roleName', $roleName)
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * آمار Role ها - برمیگردونه که هر Role چند کاربر داره
     *
     * @return array [['role_name' => 'ROLE_ADMIN', 'user_count' => 5], ...]
     */
    public function getRoleStatistics(): array
    {
        return $this->createQueryBuilder('u')
            ->select('r.name as role_name, COUNT(u.id) as user_count')
            ->innerJoin('u.userRoles', 'r')
            ->groupBy('r.name')
            ->orderBy('user_count', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }
    
    /**
     * متد قدیمی برای سازگاری با کد های قبلی - deprecated
     * @deprecated استفاده از findByRoleName یا findActiveUsersByRole
     */
    public function findByRole($value): array
    {
        // اگر کد قدیمی هنوز استفاده می‌کنه، به روش جدید redirect می‌کنیم
        return $this->findByRoleName($value);
    }
}
