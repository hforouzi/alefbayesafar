<?php

namespace App\Tests\Modules\Hotel;

use App\Modules\Default\Entity\Menu;
use App\Modules\Hotel\Entity\HotelAmenity;
use App\Modules\User\Entity\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class HotelAdminSeedCommandTest extends KernelTestCase
{
    public function testSeedCommandCreatesHotelPermissionsAndMenusIdempotently(): void
    {
        self::bootKernel();
        self::assertNotNull(self::$kernel);

        $application = new Application(self::$kernel);
        $command = $application->find('app:hotel:seed-admin');
        self::assertSame('app:hotel:seed-admin', $command->getName());

        $firstRun = new CommandTester($command);
        self::assertSame(0, $firstRun->execute([]));

        $secondRun = new CommandTester($command);
        self::assertSame(0, $secondRun->execute([]));

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        $hotelPermission = $em->getRepository(Permission::class)->findOneBy(['route' => 'hotel_index']);
        $searchPermission = $em->getRepository(Permission::class)->findOneBy(['route' => 'hotel_search']);
        $importPermission = $em->getRepository(Permission::class)->findOneBy(['route' => 'hotel_search_import']);
        $amenityPermission = $em->getRepository(Permission::class)->findOneBy(['route' => 'hotel_amenity_index']);
        $hotelMenu = $em->getRepository(Menu::class)->findOneBy(['route' => 'hotel_index']);
        $amenityMenu = $em->getRepository(Menu::class)->findOneBy(['route' => 'hotel_amenity_index']);

        self::assertInstanceOf(Permission::class, $hotelPermission);
        self::assertInstanceOf(Permission::class, $searchPermission);
        self::assertInstanceOf(Permission::class, $importPermission);
        self::assertInstanceOf(Permission::class, $amenityPermission);
        self::assertInstanceOf(Menu::class, $hotelMenu);
        self::assertInstanceOf(Menu::class, $amenityMenu);
        self::assertSame('hotel.navigation.hotels', $hotelMenu->getName());
        self::assertSame('hotel.navigation.amenities', $amenityMenu->getName());

        $amenityRepository = $em->getRepository(HotelAmenity::class);
        foreach (['wifi', 'pool', 'spa', 'parking', 'airport_shuttle', 'fitness_center', 'family_rooms', 'non_smoking_rooms', 'breakfast', 'restaurant', 'bar'] as $code) {
            self::assertInstanceOf(HotelAmenity::class, $amenityRepository->findOneBy(['code' => $code]));
        }
    }
}
