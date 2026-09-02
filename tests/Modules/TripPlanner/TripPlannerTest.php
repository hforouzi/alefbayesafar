<?php

namespace App\Tests\Modules\TripPlanner;

use App\Modules\Activity\Entity\Activity;
use App\Modules\Activity\Entity\ActivityOffer;
use App\Modules\Activity\Enum\ActivityCategory;
use App\Modules\Activity\Enum\ActivityPricingMode;
use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Flight\Entity\Airline;
use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Entity\FlightOfferLeg;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\Flight\Enum\FlightDirection;
use App\Modules\Flight\Enum\FlightPriceSourceType;
use App\Modules\Flight\Enum\FlightPricingMode;
use App\Modules\Flight\Enum\FlightTripType;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelRate;
use App\Modules\Hotel\Entity\HotelRoomType;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\Tour\Entity\ExternalTourOffer;
use App\Modules\Tour\Entity\TourPackage;
use App\Modules\Tour\Enum\TourAvailabilityStatus;
use App\Modules\Tour\Enum\TourPricingMode;
use App\Modules\Tour\Service\TourOfferResolver;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchRequest;
use App\Modules\Transfer\Entity\TransferOffer;
use App\Modules\Transfer\Entity\TransferProduct;
use App\Modules\Transfer\Enum\TransferPricingMode;
use App\Modules\TripPlanner\Enum\TripOptionType;
use App\Modules\TripPlanner\Enum\TripPlanStatus;
use App\Modules\TripPlanner\Service\TripPlanner;
use App\Modules\TripPlanner\ValueObject\TripSearchRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TripPlannerTest extends KernelTestCase
{
    public function testOwnTourRanksBeforeCheaperExternalTourAndBudgetFitIsVisible(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $planner = self::getContainer()->get(TripPlanner::class);

        [$originAirport, $destinationCity] = $this->places($em);
        $em->flush();
        $departure = new \DateTimeImmutable('2026-10-10');
        $return = new \DateTimeImmutable('2026-10-15');

        $own = (new TourPackage())
            ->setName('Own Istanbul Package ' . self::uniqueSuffix())
            ->setSlug('own-istanbul-' . strtolower(self::uniqueSuffix()))
            ->setOriginAirport($originAirport)
            ->setDestinationCity($destinationCity)
            ->setDepartureDate($departure)
            ->setReturnDate($return)
            ->setNights(5)
            ->setAdults(2)
            ->setChildren(0)
            ->setInfants(0)
            ->setPricingMode(TourPricingMode::TOTAL_PARTY)
            ->setCurrency('EUR')
            ->setTotalPrice('1490.00')
            ->setPriority(100)
            ->setActive(true)
            ->setPublicVisible(true);
        $em->persist($own);

        $requestForHash = new ExternalTourOfferSearchRequest($originAirport, $destinationCity, $departure, $return, null, null, 5, 2, 0, 0);
        $source = (new SearchSource())
            ->setName('External Test Source ' . self::uniqueSuffix())
            ->setDomain('example.test')
            ->setProvider('test')
            ->setProviderType(SearchSourceProviderType::MANUAL)
            ->setCapabilities([SearchSource::CAPABILITY_TOUR])
            ->setEnabled(true);
        $external = (new ExternalTourOffer())
            ->setSearchSource($source)
            ->setProviderCode('test')
            ->setTitle('Cheaper External Istanbul')
            ->setOriginAirport($originAirport)
            ->setOriginText('Tehran')
            ->setDestinationCity($destinationCity)
            ->setDestinationText($destinationCity->getName())
            ->setDepartureDate($departure)
            ->setReturnDate($return)
            ->setNights(5)
            ->setAdults(2)
            ->setChildren(0)
            ->setInfants(0)
            ->setCurrency('EUR')
            ->setTotalPrice('1290.00')
            ->setAvailabilityStatus(TourAvailabilityStatus::AVAILABLE)
            ->setFetchedAt(new \DateTimeImmutable('-1 hour'))
            ->setExpiresAt(new \DateTimeImmutable('+5 hours'))
            ->setMetadata(['searchContextHash' => $requestForHash->contextHash()]);
        $em->persist($source);
        $em->persist($external);
        $em->flush();
        $em->clear();

        $originAirport = $em->getRepository(Airport::class)->find($originAirport->getId());
        $destinationCity = $em->getRepository(City::class)->find($destinationCity->getId());
        self::assertInstanceOf(Airport::class, $originAirport);
        self::assertInstanceOf(City::class, $destinationCity);

        $result = $planner->plan(new TripSearchRequest($originAirport, null, $destinationCity, $departure, $return, 5, 2, 0, 0, budget: '1500.00'));

        self::assertSame(TripPlanStatus::OPTIONS_FOUND->value, $result->status->value);
        self::assertGreaterThanOrEqual(2, \count($result->options));
        self::assertSame(TripOptionType::OUR_TOUR->value, $result->options[0]->optionType->value);
        self::assertSame('1490.00', $result->options[0]->totalPrice);
        self::assertSame('within_budget', $result->options[0]->budgetStatus);
        self::assertSame(TripOptionType::EXTERNAL_TOUR->value, $result->options[1]->optionType->value);
        self::assertSame('1290.00', $result->options[1]->totalPrice);
    }

    public function testCustomCombinationAddsTransferAndActivityAndStaysBounded(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $planner = self::getContainer()->get(TripPlanner::class);

        [$originAirport, $destinationCity, $destinationAirport] = $this->places($em);
        $departure = new \DateTimeImmutable('2026-11-01 09:00:00');
        $return = new \DateTimeImmutable('2026-11-06 18:00:00');
        $hotel = $this->hotelWithRate($em, $destinationCity, 'EUR', '100.00');
        $this->flight($em, $originAirport, $destinationAirport, $departure, $return, '300.00', 'EUR');
        $this->activity($em, $destinationCity, '40.00', 'EUR');
        $this->transfer($em, $destinationCity, $hotel, '60.00', 'EUR');
        $em->flush();
        $em->clear();

        $originAirport = $em->getRepository(Airport::class)->find($originAirport->getId());
        $destinationCity = $em->getRepository(City::class)->find($destinationCity->getId());
        self::assertInstanceOf(Airport::class, $originAirport);
        self::assertInstanceOf(City::class, $destinationCity);

        $result = $planner->plan(new TripSearchRequest(
            originAirport: $originAirport,
            originCity: null,
            destinationCity: $destinationCity,
            departureDate: new \DateTimeImmutable('2026-11-01'),
            returnDate: new \DateTimeImmutable('2026-11-06'),
            nights: 5,
            adults: 2,
            children: 0,
            infants: 0,
            budget: '1000.00',
            activityCategories: [ActivityCategory::OTHER->value],
            transferRequired: true,
        ));

        self::assertSame(TripPlanStatus::OPTIONS_FOUND->value, $result->status->value);
        self::assertLessThanOrEqual(10, $result->counts['customOptions']);
        $custom = array_values(array_filter($result->options, static fn ($option): bool => $option->optionType->value === TripOptionType::CUSTOM_COMBINATION->value))[0] ?? null;
        self::assertNotNull($custom);
        self::assertSame('900.00', $custom->totalPrice);
        self::assertContains('Includes requested transfer', $custom->reasons);
        self::assertContains('Includes matching activities', $custom->reasons);
    }

    public function testMissingTransferKeepsPartialOptionWithWarning(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $planner = self::getContainer()->get(TripPlanner::class);

        [$originAirport, $destinationCity, $destinationAirport] = $this->places($em);
        $departure = new \DateTimeImmutable('2026-12-01 09:00:00');
        $return = new \DateTimeImmutable('2026-12-06 18:00:00');
        $this->hotelWithRate($em, $destinationCity, 'EUR', '100.00');
        $this->flight($em, $originAirport, $destinationAirport, $departure, $return, '300.00', 'EUR');
        $em->flush();
        $em->clear();

        $originAirport = $em->getRepository(Airport::class)->find($originAirport->getId());
        $destinationCity = $em->getRepository(City::class)->find($destinationCity->getId());
        self::assertInstanceOf(Airport::class, $originAirport);
        self::assertInstanceOf(City::class, $destinationCity);

        $result = $planner->plan(new TripSearchRequest($originAirport, null, $destinationCity, new \DateTimeImmutable('2026-12-01'), new \DateTimeImmutable('2026-12-06'), 5, 2, 0, 0, transferRequired: true));

        self::assertSame(TripPlanStatus::PARTIAL_OPTIONS->value, $result->status->value);
        self::assertSame('partial', $result->options[0]->completenessStatus);
        self::assertContains('Requested transfer is not currently available.', $result->options[0]->warnings);
    }

    public function testCurrencyMismatchIsNotSummed(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $planner = self::getContainer()->get(TripPlanner::class);

        [$originAirport, $destinationCity, $destinationAirport] = $this->places($em);
        $departure = new \DateTimeImmutable('2027-01-01 09:00:00');
        $return = new \DateTimeImmutable('2027-01-06 18:00:00');
        $this->hotelWithRate($em, $destinationCity, 'EUR', '100.00');
        $this->flight($em, $originAirport, $destinationAirport, $departure, $return, '300.00', 'USD');
        $em->flush();
        $em->clear();

        $originAirport = $em->getRepository(Airport::class)->find($originAirport->getId());
        $destinationCity = $em->getRepository(City::class)->find($destinationCity->getId());
        self::assertInstanceOf(Airport::class, $originAirport);
        self::assertInstanceOf(City::class, $destinationCity);

        $result = $planner->plan(new TripSearchRequest($originAirport, null, $destinationCity, new \DateTimeImmutable('2027-01-01'), new \DateTimeImmutable('2027-01-06'), 5, 2, 0, 0));

        self::assertSame(TripPlanStatus::PARTIAL_OPTIONS->value, $result->status->value);
        self::assertNull($result->options[0]->totalPrice);
        self::assertContains('Price cannot be directly compared because currencies differ or one component has no current price.', $result->options[0]->warnings);
    }

    public function testNoOptionsReturnsMeaningfulMessages(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $planner = self::getContainer()->get(TripPlanner::class);

        [, $destinationCity] = $this->places($em);
        $em->flush();
        $em->clear();

        $destinationCity = $em->getRepository(City::class)->find($destinationCity->getId());
        self::assertInstanceOf(City::class, $destinationCity);

        $result = $planner->plan(new TripSearchRequest(null, null, $destinationCity, new \DateTimeImmutable('2027-02-01'), null, 5, 2));

        self::assertSame(TripPlanStatus::NO_OPTIONS->value, $result->status->value);
        self::assertNotSame([], $result->messages);
        self::assertContains('No trip options could be built from the currently stored commercial data.', $result->messages);
    }

    /**
     * @return array{0: Airport, 1: City, 2: Airport}
     */
    private function places(EntityManagerInterface $em): array
    {
        $suffix = self::uniqueSuffix();
        $country = (new Country())->setName('Trip Planner Country ' . $suffix);
        $originCity = (new City())->setCountry($country)->setName('Origin City ' . $suffix)->setSlug('origin-city-' . strtolower($suffix));
        $destinationCity = (new City())->setCountry($country)->setName('Destination City ' . $suffix)->setSlug('destination-city-' . strtolower($suffix));
        $originAirport = (new Airport())->setCity($originCity)->setName('Origin Airport ' . $suffix)->setIataCode($this->uniqueIata($em));
        $destinationAirport = (new Airport())->setCity($destinationCity)->setName('Destination Airport ' . $suffix)->setIataCode($this->uniqueIata($em));
        foreach ([$country, $originCity, $destinationCity, $originAirport, $destinationAirport] as $entity) {
            $em->persist($entity);
        }

        return [$originAirport, $destinationCity, $destinationAirport];
    }

    private function hotelWithRate(EntityManagerInterface $em, City $city, string $currency, string $nightly): Hotel
    {
        $suffix = self::uniqueSuffix();
        $hotel = (new Hotel())->setCity($city)->setName('Trip Hotel ' . $suffix)->setSlug('trip-hotel-' . strtolower($suffix))->setStars(4)->setActive(true)->setVerified(true);
        $room = (new HotelRoomType())->setHotel($hotel)->setName('Double')->setCode('DBL-' . $suffix)->setActive(true);
        $rate = (new HotelRate())
            ->setHotel($hotel)
            ->setRoomType($room)
            ->setValidFrom(new \DateTimeImmutable('2026-01-01'))
            ->setValidTo(new \DateTimeImmutable('2027-12-31'))
            ->setAdults(2)
            ->setChildren(0)
            ->setChildrenAges([])
            ->setCurrency($currency)
            ->setPricePerNight($nightly)
            ->setPriority(100)
            ->setActive(true);
        $hotel->addRoomType($room);
        $hotel->addRate($rate);
        foreach ([$hotel, $room, $rate] as $entity) {
            $em->persist($entity);
        }

        return $hotel;
    }

    private function flight(EntityManagerInterface $em, Airport $origin, Airport $destination, \DateTimeImmutable $departure, ?\DateTimeImmutable $return, string $price, string $currency): void
    {
        $suffix = self::uniqueSuffix();
        $airline = (new Airline())->setName('Trip Airline ' . $suffix)->setActive(true);
        $offer = (new FlightOffer())
            ->setSourceType(FlightPriceSourceType::OWN)
            ->setTripType($return instanceof \DateTimeImmutable ? FlightTripType::ROUND_TRIP : FlightTripType::ONE_WAY)
            ->setPricingMode(FlightPricingMode::TOTAL_PARTY)
            ->setAdults(2)
            ->setChildren(0)
            ->setInfants(0)
            ->setCabinClass(FlightCabinClass::ECONOMY)
            ->setCurrency($currency)
            ->setTotalPrice($price)
            ->setPriority(100)
            ->setActive(true);
        $leg = (new FlightOfferLeg())
            ->setFlightOffer($offer)
            ->setDirection(FlightDirection::OUTBOUND)
            ->setSegmentIndex(0)
            ->setAirline($airline)
            ->setOriginAirport($origin)
            ->setDestinationAirport($destination)
            ->setFlightNumber('TP100')
            ->setDepartureAt($departure)
            ->setArrivalAt($departure->modify('+3 hours'));
        $offer->addLeg($leg);
        $entities = [$airline, $offer, $leg];
        if ($return instanceof \DateTimeImmutable) {
            $inbound = (new FlightOfferLeg())
                ->setFlightOffer($offer)
                ->setDirection(FlightDirection::INBOUND)
                ->setSegmentIndex(0)
                ->setAirline($airline)
                ->setOriginAirport($destination)
                ->setDestinationAirport($origin)
                ->setFlightNumber('TP101')
                ->setDepartureAt($return)
                ->setArrivalAt($return->modify('+3 hours'));
            $offer->addLeg($inbound);
            $entities[] = $inbound;
        }

        foreach ($entities as $entity) {
            $em->persist($entity);
        }
    }

    private function activity(EntityManagerInterface $em, City $city, string $price, string $currency): void
    {
        $suffix = self::uniqueSuffix();
        $activity = (new Activity())
            ->setCity($city)
            ->setName('Trip Activity ' . $suffix)
            ->setSlug('trip-activity-' . strtolower($suffix))
            ->setCategory(ActivityCategory::OTHER)
            ->setActive(true)
            ->setPublicVisible(true);
        $offer = (new ActivityOffer())
            ->setActivity($activity)
            ->setPricingMode(ActivityPricingMode::TOTAL_PARTY)
            ->setCurrency($currency)
            ->setTotalPrice($price)
            ->setPriority(100)
            ->setActive(true);
        $activity->addOffer($offer);
        $em->persist($activity);
        $em->persist($offer);
    }

    private function transfer(EntityManagerInterface $em, City $city, Hotel $hotel, string $price, string $currency): void
    {
        $product = (new TransferProduct())
            ->setName('Trip Transfer ' . self::uniqueSuffix())
            ->setOriginCity($city)
            ->setDestinationHotel($hotel)
            ->setMaxPassengers(4)
            ->setActive(true);
        $offer = (new TransferOffer())
            ->setTransferProduct($product)
            ->setPricingMode(TransferPricingMode::TOTAL_SERVICE)
            ->setCurrency($currency)
            ->setTotalPrice($price)
            ->setPriority(100)
            ->setActive(true);
        $product->addOffer($offer);
        $em->persist($product);
        $em->persist($offer);
    }

    private static function uniqueSuffix(): string
    {
        $letters = '';
        for ($index = 0; $index < 8; $index++) {
            $letters .= chr(random_int(65, 90));
        }

        return $letters;
    }

    private function uniqueIata(EntityManagerInterface $em): string
    {
        do {
            $code = substr(self::uniqueSuffix(), 0, 3);
        } while ($em->getRepository(Airport::class)->findOneBy(['iataCode' => $code]) instanceof Airport);

        return $code;
    }
}
