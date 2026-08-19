<?php
namespace App\Modules\Default\Repository;

use App\Modules\Default\Entity\AppSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppSetting>
 */
class AppSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppSetting::class);
    }
    
    // Custom query methods can be added here
    public function findOneByName(string $name): ?AppSetting
    {
        return $this->findOneBy(['name' => $name]);
    }
    
    public function findAllSettings(): array
    {
        return $this->findAll();
    }
}
