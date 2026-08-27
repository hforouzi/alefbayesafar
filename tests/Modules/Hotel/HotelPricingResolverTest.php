<?php

namespace App\Tests\Modules\Hotel;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelOffer;
use App\Modules\Hotel\Entity\HotelRate;
use App\Modules\Hotel\Entity\HotelRoomType;
use App\Modules\Hotel\Enum\HotelPriceSourceType;
use App\Modules\Hotel\Repository\HotelOfferRepository;
use App\Modules\Hotel\Repository\HotelRateRepository;
use App\Modules\Hotel\Service\HotelPricingResolver;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class HotelPricingResolverTest extends KernelTestCase
{
    public function testResolverReturnsOwnRatesFirstEvenWhenExternalOfferIsCheaper(): void
    {
        self::bootKernel();
        $em = $this->entityManager();
        $this->ensureCommerceSchema();
        $suffix = self::uniqueSuffix();
        $hotel = $this->hotel($suffix, $em);
        $roomType = (new HotelRoomType())
            ->setHotel($hotel)
            ->setName('Standard Double')
            ->setMaxAdults(2)
            ->setMaxChildren(1)
            ->setMaxOccupancy(3);
        $source = $this->source($suffix);
        $ownRate = $this->rate($hotel, $roomType, '124.00', 100);
        $secondOwnRate = $this->rate($hotel, $roomType, '130.00', 90)->setBoardType('Breakfast');
        $inactiveRate = $this->rate($hotel, $roomType, '80.00', 120)->setActive(false);
        $mismatchedChildAgeRate = $this->rate($hotel, $roomType, '90.00', 110)->setChildrenAges([8]);
        $external = $this->offer($hotel, $source, '590.00', HotelOffer::AVAILABILITY_AVAILABLE);
        $unavailableExternal = $this->offer($hotel, $source, '400.00', HotelOffer::AVAILABILITY_UNAVAILABLE)->setExternalOfferId('unavailable-' . $suffix);

        foreach ([$hotel, $roomType, $source, $ownRate, $secondOwnRate, $inactiveRate, $mismatchedChildAgeRate, $external, $unavailableExternal] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $em->clear();

        $hotel = $em->getRepository(Hotel::class)->find($hotel->getId());
        self::assertInstanceOf(Hotel::class, $hotel);

        $rateRepository = $em->getRepository(HotelRate::class);
        $offerRepository = $em->getRepository(HotelOffer::class);
        self::assertInstanceOf(HotelRateRepository::class, $rateRepository);
        self::assertInstanceOf(HotelOfferRepository::class, $offerRepository);

        $resolver = new HotelPricingResolver($rateRepository, $offerRepository);

        $candidates = $resolver->resolve(
            $hotel,
            new \DateTimeImmutable('2026-09-10'),
            new \DateTimeImmutable('2026-09-15'),
            2,
            1,
            [4],
        );

        self::assertCount(3, $candidates);
        self::assertSame(HotelPriceSourceType::OWN, $candidates[0]->sourceType);
        self::assertSame(100, $candidates[0]->priority);
        self::assertSame('620.00', $candidates[0]->totalPrice);
        self::assertSame('124.00', $candidates[0]->pricePerNight);
        self::assertSame(HotelPriceSourceType::OWN, $candidates[1]->sourceType);
        self::assertSame('650.00', $candidates[1]->totalPrice);
        self::assertSame(HotelPriceSourceType::EXTERNAL, $candidates[2]->sourceType);
        self::assertSame(50, $candidates[2]->priority);
        self::assertSame('590.00', $candidates[2]->totalPrice);
    }

    private function hotel(string $suffix, EntityManagerInterface $em): Hotel
    {
        $country = (new Country())->setName('Pricing Country ' . $suffix);
        $city = (new City())
            ->setCountry($country)
            ->setName('Pricing City ' . $suffix)
            ->setSlug('pricing-city-' . strtolower($suffix));
        $em->persist($country);
        $em->persist($city);

        return (new Hotel())
            ->setCity($city)
            ->setName('Pricing Hotel ' . $suffix)
            ->setSlug('pricing-hotel-' . strtolower($suffix));
    }

    private function rate(Hotel $hotel, HotelRoomType $roomType, string $pricePerNight, int $priority): HotelRate
    {
        return (new HotelRate())
            ->setHotel($hotel)
            ->setRoomType($roomType)
            ->setValidFrom(new \DateTimeImmutable('2026-09-01'))
            ->setValidTo(new \DateTimeImmutable('2026-09-30'))
            ->setAdults(2)
            ->setChildren(1)
            ->setChildrenAges([4])
            ->setCurrency('EUR')
            ->setPricePerNight($pricePerNight)
            ->setPriority($priority);
    }

    private function offer(Hotel $hotel, SearchSource $source, string $price, string $availability): HotelOffer
    {
        return (new HotelOffer())
            ->setHotel($hotel)
            ->setSearchSource($source)
            ->setProviderCode('firecrawl')
            ->setExternalOfferId('external-' . self::uniqueSuffix())
            ->setCheckIn(new \DateTimeImmutable('2026-09-10'))
            ->setCheckOut(new \DateTimeImmutable('2026-09-15'))
            ->setAdults(2)
            ->setChildren(1)
            ->setChildrenAges([4])
            ->setCurrency('EUR')
            ->setTotalPrice($price)
            ->setAvailabilityStatus($availability)
            ->setFetchedAt(new \DateTimeImmutable('2026-08-27 10:00:00'));
    }

    private function source(string $suffix): SearchSource
    {
        return (new SearchSource())
            ->setName('Booking Pricing ' . $suffix)
            ->setDomain('booking-pricing-' . strtolower($suffix) . '.example.test')
            ->setProvider('firecrawl')
            ->setProviderType(SearchSourceProviderType::FIRECRAWL)
            ->setCapabilities([SearchSource::CAPABILITY_HOTEL])
            ->setEnabled(true);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function ensureCommerceSchema(): void
    {
        $connection = $this->entityManager()->getConnection();
        $schemaManager = $connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['hotel_room_type'])) {
            $connection->executeStatement('CREATE TABLE hotel_room_type (id INT AUTO_INCREMENT NOT NULL, hotel_id INT NOT NULL, name VARCHAR(180) NOT NULL, name_fa VARCHAR(180) DEFAULT NULL, code VARCHAR(64) DEFAULT NULL, max_adults SMALLINT DEFAULT NULL, max_children SMALLINT DEFAULT NULL, max_occupancy SMALLINT DEFAULT NULL, description_original LONGTEXT DEFAULT NULL, description_fa LONGTEXT DEFAULT NULL, bed_configuration VARCHAR(255) DEFAULT NULL, size_sqm NUMERIC(7, 2) DEFAULT NULL, source VARCHAR(64) DEFAULT NULL, external_id VARCHAR(190) DEFAULT NULL, source_url VARCHAR(2048) DEFAULT NULL, source_name VARCHAR(180) DEFAULT NULL, metadata JSON NOT NULL COMMENT \'(DC2Type:json)\', active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_hotel_room_type_hotel_active (hotel_id, active), INDEX idx_hotel_room_type_source_external (hotel_id, source, external_id), UNIQUE INDEX uniq_hotel_room_type_hotel_code (hotel_id, code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $connection->executeStatement('ALTER TABLE hotel_room_type ADD CONSTRAINT FK_6BC2782C3243BB18 FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
        }

        if (!$schemaManager->tablesExist(['hotel_rate'])) {
            $connection->executeStatement('CREATE TABLE hotel_rate (id INT AUTO_INCREMENT NOT NULL, hotel_id INT NOT NULL, room_type_id INT NOT NULL, valid_from DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', valid_to DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', adults SMALLINT NOT NULL, children SMALLINT NOT NULL, children_ages JSON NOT NULL COMMENT \'(DC2Type:json)\', board_type VARCHAR(120) DEFAULT NULL, currency VARCHAR(3) NOT NULL, price_per_night NUMERIC(12, 2) NOT NULL, priority INT DEFAULT 100 NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_hotel_rate_hotel_active (hotel_id, active), INDEX idx_hotel_rate_room_type_active (room_type_id, active), INDEX idx_hotel_rate_validity (hotel_id, valid_from, valid_to), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $connection->executeStatement('ALTER TABLE hotel_rate ADD CONSTRAINT FK_3E3E41E93243BB18 FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
            $connection->executeStatement('ALTER TABLE hotel_rate ADD CONSTRAINT FK_3E3E41E954177093 FOREIGN KEY (room_type_id) REFERENCES hotel_room_type (id) ON DELETE RESTRICT');
        }
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
