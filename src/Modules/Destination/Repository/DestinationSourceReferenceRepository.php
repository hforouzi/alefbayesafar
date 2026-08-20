<?php

namespace App\Modules\Destination\Repository;

use App\Modules\Destination\Entity\DestinationSourceReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DestinationSourceReference>
 */
class DestinationSourceReferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DestinationSourceReference::class);
    }

    public function findForExternalId(string $source, string $canonicalType, string $externalId): ?DestinationSourceReference
    {
        return $this->findOneBy([
            'source' => strtolower($source),
            'canonicalType' => $canonicalType,
            'externalId' => $externalId,
        ]);
    }

    /**
     * @return DestinationSourceReference[]
     */
    public function findForCanonical(string $canonicalType, int $canonicalId): array
    {
        return $this->findBy([
            'canonicalType' => $canonicalType,
            'canonicalId' => $canonicalId,
        ], ['source' => 'ASC']);
    }
}
