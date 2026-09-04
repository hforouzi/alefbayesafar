<?php

namespace App\Tests\Modules\Hotel;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelOffer;
use App\Modules\Hotel\Repository\HotelOfferRepository;
use App\Modules\Hotel\Service\HotelOfferStoreService;
use App\Modules\Hotel\ValueObject\HotelOfferCandidate;
use App\Modules\Hotel\ValueObject\HotelOfferSearchRequest;
use App\Modules\Hotel\ValueObject\HotelOfferSearchResult;
use App\Modules\Hotel\ValueObject\HotelOfferSearchSummary;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class HotelOfferStoreServiceTest extends KernelTestCase
{
    public function testSuccessfulResultReplacesExactContextAndMapsCandidates(): void
    {
        self::bootKernel();
        $this->ensureHotelOfferSchema();
        $em = $this->entityManager();
        $suffix = self::uniqueSuffix();
        $hotel = $this->persistHotel($suffix);
        $source = $this->persistSource('Booking ' . $suffix, 'booking-' . strtolower($suffix) . '.example.test');
        $otherSource = $this->persistSource('Agoda ' . $suffix, 'agoda-' . strtolower($suffix) . '.example.test');
        $request = $this->request();
        $otherDateRequest = new HotelOfferSearchRequest(new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-05'), 2, 1, [4]);
        $otherTravelerRequest = new HotelOfferSearchRequest(new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), 1, 0);
        $otherAgeRequest = new HotelOfferSearchRequest(new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), 2, 1, [15]);

        $oldExactId = 'old-exact-' . $suffix;
        $this->persistOffer($hotel, $source, $request, $oldExactId, '111.00');
        $otherSourceOffer = $this->persistOffer($hotel, $otherSource, $request, 'other-source-' . $suffix, '222.00');
        $otherDateOffer = $this->persistOffer($hotel, $source, $otherDateRequest, 'other-date-' . $suffix, '333.00');
        $otherTravelerOffer = $this->persistOffer($hotel, $source, $otherTravelerRequest, 'other-travelers-' . $suffix, '444.00');
        $otherAgeOffer = $this->persistOffer($hotel, $source, $otherAgeRequest, 'other-age-' . $suffix, '445.00');
        $em->flush();

        $fetchedAt = new \DateTimeImmutable('2026-08-24 12:00:00');
        $stored = $this->storeService()->storeResult($hotel, $request, HotelOfferSearchResult::success($source, [
            $this->candidate('rate-1-' . $suffix, 'Deluxe Double Room', 'Breakfast included', '620.00'),
            $this->candidate(null, null, null, '700.50', ['batch' => 'second']),
        ]), $fetchedAt);

        self::assertSame(2, $stored);
        $em->clear();

        $offers = $this->offersForHotel($hotel);
        self::assertCount(6, $offers);
        self::assertNull($this->findByExternalId($oldExactId));
        self::assertNotNull($this->findByExternalId((string) $otherSourceOffer->getExternalOfferId()));
        self::assertNotNull($this->findByExternalId((string) $otherDateOffer->getExternalOfferId()));
        self::assertNotNull($this->findByExternalId((string) $otherTravelerOffer->getExternalOfferId()));
        self::assertNotNull($this->findByExternalId((string) $otherAgeOffer->getExternalOfferId()));

        $storedOffer = $this->findByExternalId('rate-1-' . $suffix);
        self::assertInstanceOf(HotelOffer::class, $storedOffer);
        self::assertSame($source->getId(), $storedOffer->getSearchSource()?->getId());
        self::assertSame('firecrawl', $storedOffer->getProviderCode());
        self::assertSame('620.00', $storedOffer->getTotalPrice());
        self::assertIsString($storedOffer->getTotalPrice());
        self::assertSame('2026-09-10', $storedOffer->getCheckIn()?->format('Y-m-d'));
        self::assertSame('2026-09-15', $storedOffer->getCheckOut()?->format('Y-m-d'));
        self::assertSame(2, $storedOffer->getAdults());
        self::assertSame(1, $storedOffer->getChildren());
        self::assertSame([4], $storedOffer->getChildrenAges());
        self::assertSame('Deluxe Double Room', $storedOffer->getRoomName());
        self::assertSame('Breakfast included', $storedOffer->getBoardType());
        self::assertSame('EUR', $storedOffer->getCurrency());
        self::assertSame('https://booking.example.test/rate-1-' . $suffix, $storedOffer->getBookingUrl());
        self::assertSame(HotelOffer::AVAILABILITY_AVAILABLE, $storedOffer->getAvailabilityStatus());
        self::assertSame(['sourcePayload' => ['rate' => 'rate-1-' . $suffix]], $storedOffer->getMetadata());
        self::assertSame('2026-08-24 12:00:00', $storedOffer->getFetchedAt()?->format('Y-m-d H:i:s'));

        $nullableOffer = $this->entityManager()->getRepository(HotelOffer::class)->findOneBy(['totalPrice' => '700.50']);
        self::assertInstanceOf(HotelOffer::class, $nullableOffer);
        self::assertNull($nullableOffer->getExternalOfferId());
        self::assertNull($nullableOffer->getRoomName());
        self::assertNull($nullableOffer->getBoardType());
        self::assertSame(['batch' => 'second'], $nullableOffer->getMetadata());
    }

    public function testFailureResultLeavesExistingOffersUntouched(): void
    {
        self::bootKernel();
        $this->ensureHotelOfferSchema();
        $suffix = self::uniqueSuffix();
        $hotel = $this->persistHotel('FAIL' . $suffix);
        $source = $this->persistSource('Failure Source ' . $suffix, 'failure-' . strtolower($suffix) . '.example.test');
        $request = $this->request();
        $keepOnFailureId = 'keep-on-failure-' . $suffix;
        $this->persistOffer($hotel, $source, $request, $keepOnFailureId, '555.00');
        $this->entityManager()->flush();

        $stored = $this->storeService()->storeResult($hotel, $request, HotelOfferSearchResult::failure($source, ['Provider failed']));

        self::assertSame(0, $stored);
        self::assertInstanceOf(HotelOffer::class, $this->findByExternalId($keepOnFailureId));
    }

    public function testNoDataResultLeavesExistingOffersUntouched(): void
    {
        self::bootKernel();
        $this->ensureHotelOfferSchema();
        $suffix = self::uniqueSuffix();
        $hotel = $this->persistHotel('ZERO' . $suffix);
        $source = $this->persistSource('Zero Source ' . $suffix, 'zero-' . strtolower($suffix) . '.example.test');
        $otherSource = $this->persistSource('Zero Other ' . $suffix, 'zero-other-' . strtolower($suffix) . '.example.test');
        $request = $this->request();
        $removeZeroId = 'remove-zero-' . $suffix;
        $keepOtherSourceId = 'keep-other-source-zero-' . $suffix;
        $this->persistOffer($hotel, $source, $request, $removeZeroId, '555.00');
        $this->persistOffer($hotel, $source, new HotelOfferSearchRequest(new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), 2, 1, [15]), 'keep-other-age-zero-' . $suffix, '556.00');
        $this->persistOffer($hotel, $otherSource, $request, $keepOtherSourceId, '666.00');
        $this->entityManager()->flush();

        $stored = $this->storeService()->storeResult($hotel, $request, HotelOfferSearchResult::noData($source));

        self::assertSame(0, $stored);
        self::assertInstanceOf(HotelOffer::class, $this->findByExternalId($removeZeroId));
        self::assertInstanceOf(HotelOffer::class, $this->findByExternalId('keep-other-age-zero-' . $suffix));
        self::assertInstanceOf(HotelOffer::class, $this->findByExternalId($keepOtherSourceId));
    }

    public function testStoreSummaryStoresSuccessfulResultsAndSkipsFailures(): void
    {
        self::bootKernel();
        $this->ensureHotelOfferSchema();
        $suffix = self::uniqueSuffix();
        $hotel = $this->persistHotel('SUM' . $suffix);
        $source = $this->persistSource('Summary Source ' . $suffix, 'summary-' . strtolower($suffix) . '.example.test');
        $failedSource = $this->persistSource('Summary Failed ' . $suffix, 'summary-failed-' . strtolower($suffix) . '.example.test');
        $request = $this->request();
        $keepSummaryFailureId = 'keep-summary-failure-' . $suffix;
        $summaryRateId = 'summary-rate-' . $suffix;
        $this->persistOffer($hotel, $failedSource, $request, $keepSummaryFailureId, '888.00');
        $this->entityManager()->flush();

        $stored = $this->storeService()->storeSummary($hotel, $request, new HotelOfferSearchSummary([
            HotelOfferSearchResult::success($source, [$this->candidate($summaryRateId, 'Suite', 'Half board', '900.00')]),
            HotelOfferSearchResult::failure($failedSource, ['Timeout']),
        ]), new \DateTimeImmutable('2026-08-24 13:00:00'));

        self::assertSame(1, $stored);
        self::assertInstanceOf(HotelOffer::class, $this->findByExternalId($summaryRateId));
        self::assertInstanceOf(HotelOffer::class, $this->findByExternalId($keepSummaryFailureId));
    }

    private function persistHotel(string $suffix): Hotel
    {
        $country = (new Country())->setName('Offer Country ' . $suffix);
        $city = (new City())
            ->setCountry($country)
            ->setName('Offer City ' . $suffix)
            ->setSlug('offer-city-' . strtolower($suffix));
        $hotel = (new Hotel())
            ->setCity($city)
            ->setName('Offer Hotel ' . $suffix)
            ->setSlug('offer-hotel-' . strtolower($suffix));

        $em = $this->entityManager();
        $em->persist($country);
        $em->persist($city);
        $em->persist($hotel);
        $em->flush();

        return $hotel;
    }

    private function persistSource(string $name, string $domain): SearchSource
    {
        $source = (new SearchSource())
            ->setName($name)
            ->setDomain($domain)
            ->setProvider('firecrawl')
            ->setProviderType(SearchSourceProviderType::FIRECRAWL)
            ->setCapabilities([SearchSource::CAPABILITY_HOTEL])
            ->setEnabled(false);

        $em = $this->entityManager();
        $em->persist($source);
        $em->flush();

        return $source;
    }

    private function persistOffer(Hotel $hotel, SearchSource $source, HotelOfferSearchRequest $request, string $externalId, string $price): HotelOffer
    {
        $offer = (new HotelOffer())
            ->setHotel($hotel)
            ->setSearchSource($source)
            ->setProviderCode('firecrawl')
            ->setExternalOfferId($externalId)
            ->setCheckIn($request->checkIn)
            ->setCheckOut($request->checkOut)
            ->setAdults($request->adults)
            ->setChildren($request->children)
            ->setChildrenAges($request->childrenAges)
            ->setCurrency('EUR')
            ->setTotalPrice($price)
            ->setFetchedAt(new \DateTimeImmutable('2026-08-23 12:00:00'));

        $this->entityManager()->persist($offer);

        return $offer;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function candidate(?string $externalId, ?string $roomName, ?string $boardType, string $price, array $metadata = []): HotelOfferCandidate
    {
        return new HotelOfferCandidate(
            sourceIdentifier: 'booking',
            sourceName: 'Booking',
            providerCode: 'firecrawl',
            externalOfferId: $externalId,
            roomName: $roomName,
            boardType: $boardType,
            currency: 'EUR',
            totalPrice: $price,
            bookingUrl: $externalId !== null ? 'https://booking.example.test/' . $externalId : null,
            availabilityStatus: HotelOffer::AVAILABILITY_AVAILABLE,
            metadata: $metadata !== [] ? $metadata : ['sourcePayload' => ['rate' => $externalId]],
        );
    }

    private function request(): HotelOfferSearchRequest
    {
        return new HotelOfferSearchRequest(
            new \DateTimeImmutable('2026-09-10'),
            new \DateTimeImmutable('2026-09-15'),
            2,
            1,
            [4],
        );
    }

    /**
     * @return HotelOffer[]
     */
    private function offersForHotel(Hotel $hotel): array
    {
        return $this->entityManager()->getRepository(HotelOffer::class)->findBy(['hotel' => $hotel], ['externalOfferId' => 'ASC']);
    }

    private function findByExternalId(string $externalId): ?HotelOffer
    {
        $offer = $this->entityManager()->getRepository(HotelOffer::class)->findOneBy(['externalOfferId' => $externalId]);

        return $offer instanceof HotelOffer ? $offer : null;
    }

    private function storeService(): HotelOfferStoreService
    {
        $em = $this->entityManager();
        $repository = $em->getRepository(HotelOffer::class);
        self::assertInstanceOf(HotelOfferRepository::class, $repository);

        return new HotelOfferStoreService($em, $repository);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function ensureHotelOfferSchema(): void
    {
        $em = $this->entityManager();
        if ($em->getConnection()->createSchemaManager()->tablesExist(['hotel_offer'])) {
            if (!$em->getConnection()->createSchemaManager()->introspectTable('hotel_offer')->hasColumn('children_ages')) {
                $em->getConnection()->executeStatement('ALTER TABLE hotel_offer ADD children_ages JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
                $em->getConnection()->executeStatement('UPDATE hotel_offer SET children_ages = \'[]\' WHERE children_ages IS NULL');
                $em->getConnection()->executeStatement('ALTER TABLE hotel_offer CHANGE children_ages children_ages JSON NOT NULL COMMENT \'(DC2Type:json)\'');
            }

            return;
        }

        $em->getConnection()->executeStatement('CREATE TABLE hotel_offer (id INT AUTO_INCREMENT NOT NULL, hotel_id INT NOT NULL, search_source_id INT NOT NULL, provider_code VARCHAR(64) NOT NULL, external_offer_id VARCHAR(190) DEFAULT NULL, check_in DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', check_out DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', adults SMALLINT NOT NULL, children SMALLINT NOT NULL, children_ages JSON NOT NULL COMMENT \'(DC2Type:json)\', room_name VARCHAR(255) DEFAULT NULL, board_type VARCHAR(120) DEFAULT NULL, currency VARCHAR(3) NOT NULL, total_price NUMERIC(12, 2) NOT NULL, booking_url VARCHAR(2048) DEFAULT NULL, availability_status VARCHAR(32) DEFAULT NULL, fetched_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', metadata JSON NOT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_hotel_offer_hotel (hotel_id), INDEX idx_hotel_offer_search_source (search_source_id), INDEX idx_hotel_offer_fetched_at (fetched_at), INDEX idx_hotel_offer_stay_travelers (hotel_id, check_in, check_out, adults, children), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $em->getConnection()->executeStatement('ALTER TABLE hotel_offer ADD CONSTRAINT FK_CA0249FA3243BB18 FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
        $em->getConnection()->executeStatement('ALTER TABLE hotel_offer ADD CONSTRAINT FK_CA0249FA2E270CC9 FOREIGN KEY (search_source_id) REFERENCES search_source (id) ON DELETE RESTRICT');
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
