<?php

namespace App\Modules\Activity\Repository;

use App\Modules\Activity\Entity\Activity;
use App\Modules\Activity\Entity\ActivityOffer;
use App\Modules\Activity\Enum\ActivityCategory;
use App\Modules\Destination\Entity\City;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityOffer>
 */
class ActivityOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityOffer::class);
    }

    /**
     * @return ActivityOffer[]
     */
    public function findForActivity(Activity $activity): array
    {
        return $this->createQueryBuilder('offer')
            ->andWhere('offer.activity = :activity')
            ->setParameter('activity', $activity)
            ->orderBy('offer.active', 'DESC')
            ->addOrderBy('offer.priority', 'DESC')
            ->addOrderBy('offer.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cheap pre-filter for the resolver: active offers belonging to active,
     * public-visible activities in the requested city (and category, when
     * given). Final date/participant applicability is checked in PHP via
     * ActivityOffer::matches().
     *
     * @return ActivityOffer[]
     */
    public function findCandidatesForSearch(City $city, ?ActivityCategory $category = null): array
    {
        $builder = $this->createQueryBuilder('offer')
            ->innerJoin('offer.activity', 'activity')
            ->addSelect('activity')
            ->andWhere('offer.active = true')
            ->andWhere('activity.active = true')
            ->andWhere('activity.publicVisible = true')
            ->andWhere('activity.city = :city')
            ->setParameter('city', $city);

        if ($category instanceof ActivityCategory) {
            $builder->andWhere('activity.category = :category')->setParameter('category', $category);
        }

        return $builder->getQuery()->getResult();
    }
}
