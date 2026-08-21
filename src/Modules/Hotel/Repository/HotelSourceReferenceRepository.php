<?php

namespace App\Modules\Hotel\Repository;

use App\Modules\Hotel\Entity\HotelSourceReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HotelSourceReference>
 */
class HotelSourceReferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HotelSourceReference::class);
    }

    public function findOneBySourceAndExternalId(string $source, string $externalId): ?HotelSourceReference
    {
        $result = $this->findOneBy([
            'source' => strtolower(trim($source)),
            'externalId' => trim($externalId),
        ]);

        return $result instanceof HotelSourceReference ? $result : null;
    }
}
