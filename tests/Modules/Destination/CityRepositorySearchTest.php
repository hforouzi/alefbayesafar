<?php

namespace App\Tests\Modules\Destination;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Repository\CityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Regression coverage for Persian conversational city lookup: once a city
 * has a Persian name (name_fa), CityRepository::search() must resolve it by
 * either Persian or English text — this is exactly what
 * ConversationalTravelPlanningService relies on for messages like "تهران".
 */
class CityRepositorySearchTest extends KernelTestCase
{
    public function testTehranResolvesByPersianQuery(): void
    {
        self::bootKernel();
        [$em, $city] = $this->cityFixture('Tehran', 'تهران');

        $matches = $this->repository($em)->search('تهران', null, null, 200);

        self::assertContains($city->getId(), array_map(static fn (City $c): ?int => $c->getId(), $matches));
    }

    public function testRashtResolvesByPersianQuery(): void
    {
        self::bootKernel();
        [$em, $city] = $this->cityFixture('Rasht', 'رشت');

        $matches = $this->repository($em)->search('رشت', null, null, 200);

        self::assertContains($city->getId(), array_map(static fn (City $c): ?int => $c->getId(), $matches));
    }

    public function testIstanbulResolvesByPersianQuery(): void
    {
        self::bootKernel();
        [$em, $city] = $this->cityFixture('Istanbul', 'استانبول');

        $matches = $this->repository($em)->search('استانبول', null, null, 200);

        self::assertContains($city->getId(), array_map(static fn (City $c): ?int => $c->getId(), $matches));
    }

    public function testEnglishNameQueryStillResolvesTheSameCities(): void
    {
        self::bootKernel();
        [$em, $tehran] = $this->cityFixture('Tehran', 'تهران');
        [, $rasht] = $this->cityFixture('Rasht', 'رشت', $em);
        [, $istanbul] = $this->cityFixture('Istanbul', 'استانبول', $em);

        $repository = $this->repository($em);
        self::assertContains($tehran->getId(), array_map(static fn (City $c): ?int => $c->getId(), $repository->search('Tehran', null, null, 500)));
        self::assertContains($rasht->getId(), array_map(static fn (City $c): ?int => $c->getId(), $repository->search('Rasht', null, null, 500)));
        self::assertContains($istanbul->getId(), array_map(static fn (City $c): ?int => $c->getId(), $repository->search('Istanbul', null, null, 500)));
    }

    /**
     * @return array{0: EntityManagerInterface, 1: City}
     */
    private function cityFixture(string $name, string $nameFa, ?EntityManagerInterface $em = null): array
    {
        $em ??= self::getContainer()->get(EntityManagerInterface::class);
        $suffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $country = (new Country())->setName('Search Test Country ' . $suffix);
        $city = (new City())
            ->setCountry($country)
            ->setName($name . ' ' . $suffix)
            ->setNameFa($nameFa)
            ->setSlug('search-test-' . strtolower($name) . '-' . strtolower($suffix));
        $em->persist($country);
        $em->persist($city);
        $em->flush();

        return [$em, $city];
    }

    private function repository(EntityManagerInterface $em): CityRepository
    {
        $repository = $em->getRepository(City::class);
        self::assertInstanceOf(CityRepository::class, $repository);

        return $repository;
    }
}
