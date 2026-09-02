<?php

namespace App\Tests\Modules\Activity;

use App\Modules\Activity\Entity\Activity;
use App\Modules\Activity\Entity\ActivityOffer;
use App\Modules\Activity\Enum\ActivityCategory;
use App\Modules\Activity\Enum\ActivityPricingMode;
use App\Modules\Activity\Service\ActivityOfferResolver;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ActivityOfferResolverTest extends KernelTestCase
{
    public function testResolverReturnsOnlyApplicableOffersOrderedByPriorityThenPrice(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $resolver = new ActivityOfferResolver($em->getRepository(ActivityOffer::class));

        $suffix = self::uniqueSuffix();
        $country = (new Country())->setName('Resolver Country ' . $suffix);
        $city = (new City())->setCountry($country)->setName('Resolver City ' . $suffix)->setSlug('resolver-city-' . strtolower($suffix));
        $em->persist($country);
        $em->persist($city);

        $cheapHighPriority = $this->activity($city, 'cheap-' . strtolower($suffix), 100, '30.00');
        $expensiveHigherPriority = $this->activity($city, 'expensive-' . strtolower($suffix), 200, '50.00');
        $inactive = $this->activity($city, 'inactive-' . strtolower($suffix), 100, '10.00');
        $inactive->setActive(false);
        $wrongCity = $this->activity($this->otherCity($em), 'wrong-city-' . strtolower($suffix), 100, '5.00');

        foreach ([$cheapHighPriority, $expensiveHigherPriority, $inactive, $wrongCity] as $activity) {
            $em->persist($activity);
            foreach ($activity->getOffers() as $offer) {
                $em->persist($offer);
            }
        }
        $em->flush();
        $em->clear();

        $reloadedCity = $em->getRepository(City::class)->find($city->getId());
        self::assertInstanceOf(City::class, $reloadedCity);

        $candidates = $resolver->resolve($reloadedCity, new \DateTimeImmutable('2026-10-10'), 2, 0, 0);

        self::assertCount(2, $candidates);
        self::assertSame($expensiveHigherPriority->getSlug(), $candidates[0]->activity->getSlug());
        self::assertSame($cheapHighPriority->getSlug(), $candidates[1]->activity->getSlug());
    }

    public function testResolverExcludesOffersOutsideValidityWindow(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $resolver = new ActivityOfferResolver($em->getRepository(ActivityOffer::class));

        $suffix = self::uniqueSuffix();
        $country = (new Country())->setName('Resolver Date Country ' . $suffix);
        $city = (new City())->setCountry($country)->setName('Resolver Date City ' . $suffix)->setSlug('resolver-date-city-' . strtolower($suffix));
        $em->persist($country);
        $em->persist($city);

        $activity = (new Activity())
            ->setCity($city)
            ->setName('Past Window Activity')
            ->setSlug('past-window-' . strtolower($suffix))
            ->setCategory(ActivityCategory::OTHER)
            ->setPublicVisible(true)
            ->setActive(true);
        $offer = (new ActivityOffer())
            ->setActivity($activity)
            ->setCurrency('EUR')
            ->setPricingMode(ActivityPricingMode::TOTAL_PARTY)
            ->setTotalPrice('99.00')
            ->setValidFrom(new \DateTimeImmutable('2020-01-01'))
            ->setValidTo(new \DateTimeImmutable('2020-01-31'));
        $activity->addOffer($offer);

        $em->persist($activity);
        $em->persist($offer);
        $em->flush();
        $em->clear();

        $reloadedCity = $em->getRepository(City::class)->find($city->getId());
        self::assertInstanceOf(City::class, $reloadedCity);

        $candidates = $resolver->resolve($reloadedCity, new \DateTimeImmutable('2026-10-10'), 2, 0, 0);

        self::assertSame([], $candidates);
    }

    private function activity(City $city, string $slug, int $priority, string $totalPrice): Activity
    {
        $activity = (new Activity())
            ->setCity($city)
            ->setName('Activity ' . $slug)
            ->setSlug($slug)
            ->setCategory(ActivityCategory::OTHER)
            ->setPublicVisible(true)
            ->setActive(true);

        $offer = (new ActivityOffer())
            ->setActivity($activity)
            ->setCurrency('EUR')
            ->setPricingMode(ActivityPricingMode::TOTAL_PARTY)
            ->setTotalPrice($totalPrice)
            ->setPriority($priority);
        $activity->addOffer($offer);

        return $activity;
    }

    private function otherCity(EntityManagerInterface $em): City
    {
        $suffix = self::uniqueSuffix();
        $country = (new Country())->setName('Other Country ' . $suffix);
        $city = (new City())->setCountry($country)->setName('Other City ' . $suffix)->setSlug('other-city-' . strtolower($suffix));
        $em->persist($country);
        $em->persist($city);

        return $city;
    }

    private static function uniqueSuffix(): string
    {
        $letters = '';
        for ($index = 0; $index < 6; $index++) {
            $letters .= chr(random_int(65, 90));
        }

        return $letters;
    }
}
