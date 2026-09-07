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
use App\Modules\Hotel\Entity\HotelSourceReference;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\Tour\Entity\ExternalTourOffer;
use App\Modules\Tour\Entity\TourPackage;
use App\Modules\Tour\Enum\TourAvailabilityStatus;
use App\Modules\Tour\Enum\TourPricingMode;
use App\Modules\Tour\Service\TourOfferResolver;
use App\Modules\Tour\Provider\ExternalTourOfferProviderInterface;
use App\Modules\Tour\Service\ExternalTourOfferSearchService;
use App\Modules\Tour\Service\ExternalTourOfferStoreService;
use App\Modules\Tour\Service\TourSourceEligibilityService;
use App\Modules\Tour\ValueObject\ExternalTourOfferCandidate;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchResult;
use App\Modules\Tour\ValueObject\ExternalTourOfferSearchRequest;
use App\Modules\Transfer\Entity\TransferOffer;
use App\Modules\Transfer\Entity\TransferProduct;
use App\Modules\Transfer\Enum\TransferPricingMode;
use App\Modules\TripPlanner\Enum\TripDateMode;
use App\Modules\TripPlanner\Enum\TripPlanningGoal;
use App\Modules\TripPlanner\Enum\TripOptionType;
use App\Modules\TripPlanner\Enum\TripPlanStatus;
use App\Modules\TripPlanner\Service\JourneyStrategyPlanner;
use App\Modules\TripPlanner\Service\PlanningIntentClarifier;
use App\Modules\TripPlanner\Service\TravelPlanningService;
use App\Modules\TripPlanner\Service\TripPlanner;
use App\Modules\TripPlanner\ValueObject\TripSearchRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TripPlannerTest extends KernelTestCase
{
    public function testDateModeValidation(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        [$originAirport, $destinationCity] = $this->places($em);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Exact date searches require departure and return dates.');
        new TripSearchRequest($originAirport, null, $destinationCity, new \DateTimeImmutable('2026-10-10'), null, 5, 2);
    }

    public function testFlexibleDateModeValidation(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        [$originAirport, $destinationCity] = $this->places($em);

        $valid = new TripSearchRequest(
            originAirport: $originAirport,
            originCity: null,
            destinationCity: $destinationCity,
            departureDate: null,
            returnDate: null,
            nights: 5,
            adults: 2,
            dateMode: TripDateMode::FLEXIBLE,
            windowStart: new \DateTimeImmutable('2026-09-23'),
            windowEnd: new \DateTimeImmutable('2026-10-22'),
        );
        self::assertTrue($valid->isFlexible());
        self::assertSame('2026-09-23', $valid->travelStartDate()->format('Y-m-d'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Flexible date searches require nights.');
        new TripSearchRequest(
            originAirport: $originAirport,
            originCity: null,
            destinationCity: $destinationCity,
            departureDate: null,
            returnDate: null,
            nights: null,
            adults: 2,
            dateMode: TripDateMode::FLEXIBLE,
            windowStart: new \DateTimeImmutable('2026-09-23'),
            windowEnd: new \DateTimeImmutable('2026-10-22'),
        );
    }

    public function testOriginScopeIsValidWithCityOnly(): void
    {
        $suffix = self::uniqueSuffix();
        $iran = (new Country())->setName('Iran ' . $suffix);
        $rasht = (new City())->setCountry($iran)->setName('Rasht ' . $suffix)->setSlug('rasht-' . strtolower($suffix));
        $turkey = (new Country())->setName('Turkey ' . $suffix);
        $istanbul = (new City())->setCountry($turkey)->setName('Istanbul ' . $suffix)->setSlug('istanbul-' . strtolower($suffix));

        $request = new TripSearchRequest(
            originAirport: null,
            originCity: $rasht,
            destinationCity: $istanbul,
            departureDate: new \DateTimeImmutable('2026-10-10'),
            returnDate: new \DateTimeImmutable('2026-10-15'),
            nights: 5,
            adults: 2,
        );

        self::assertTrue($request->hasOriginScope());
    }

    public function testOriginScopeIsValidWithAirportOnly(): void
    {
        $suffix = self::uniqueSuffix();
        $iran = (new Country())->setName('Iran ' . $suffix);
        $tehran = (new City())->setCountry($iran)->setName('Tehran ' . $suffix)->setSlug('tehran-' . strtolower($suffix));
        $ika = (new Airport())->setCity($tehran)->setName('Imam Khomeini International Airport ' . $suffix)->setIataCode('IKA');
        $turkey = (new Country())->setName('Turkey ' . $suffix);
        $istanbul = (new City())->setCountry($turkey)->setName('Istanbul ' . $suffix)->setSlug('istanbul-' . strtolower($suffix));

        $request = new TripSearchRequest(
            originAirport: $ika,
            originCity: null,
            destinationCity: $istanbul,
            departureDate: new \DateTimeImmutable('2026-10-10'),
            returnDate: new \DateTimeImmutable('2026-10-15'),
            nights: 5,
            adults: 2,
        );

        self::assertTrue($request->hasOriginScope());
    }

    public function testOriginScopeIsInvalidWithoutCityOrAirport(): void
    {
        $suffix = self::uniqueSuffix();
        $turkey = (new Country())->setName('Turkey ' . $suffix);
        $istanbul = (new City())->setCountry($turkey)->setName('Istanbul ' . $suffix)->setSlug('istanbul-' . strtolower($suffix));

        $request = new TripSearchRequest(
            originAirport: null,
            originCity: null,
            destinationCity: $istanbul,
            departureDate: new \DateTimeImmutable('2026-10-10'),
            returnDate: new \DateTimeImmutable('2026-10-15'),
            nights: 5,
            adults: 2,
        );

        self::assertFalse($request->hasOriginScope());
    }

    public function testDestinationScopeIsValidWithCityOnly(): void
    {
        $suffix = self::uniqueSuffix();
        $iran = (new Country())->setName('Iran ' . $suffix);
        $rasht = (new City())->setCountry($iran)->setName('Rasht ' . $suffix)->setSlug('rasht-' . strtolower($suffix));
        $turkey = (new Country())->setName('Turkey ' . $suffix);
        $istanbul = (new City())->setCountry($turkey)->setName('Istanbul ' . $suffix)->setSlug('istanbul-' . strtolower($suffix));

        $request = new TripSearchRequest(
            originAirport: null,
            originCity: $rasht,
            destinationCity: $istanbul,
            departureDate: new \DateTimeImmutable('2026-10-10'),
            returnDate: new \DateTimeImmutable('2026-10-15'),
            nights: 5,
            adults: 2,
        );

        self::assertTrue($request->hasDestinationScope());
    }

    public function testDestinationScopeIsValidWithCountryOnly(): void
    {
        $suffix = self::uniqueSuffix();
        $iran = (new Country())->setName('Iran ' . $suffix);
        $rasht = (new City())->setCountry($iran)->setName('Rasht ' . $suffix)->setSlug('rasht-' . strtolower($suffix));
        $turkey = (new Country())->setName('Turkey ' . $suffix);

        $request = new TripSearchRequest(
            originAirport: null,
            originCity: $rasht,
            destinationCity: null,
            departureDate: new \DateTimeImmutable('2026-10-10'),
            returnDate: new \DateTimeImmutable('2026-10-15'),
            nights: 5,
            adults: 2,
            destinationCountry: $turkey,
        );

        self::assertTrue($request->hasDestinationScope());
    }

    public function testDestinationScopeIsInvalidWithoutCityOrCountry(): void
    {
        $suffix = self::uniqueSuffix();
        $iran = (new Country())->setName('Iran ' . $suffix);
        $rasht = (new City())->setCountry($iran)->setName('Rasht ' . $suffix)->setSlug('rasht-' . strtolower($suffix));

        $request = new TripSearchRequest(
            originAirport: null,
            originCity: $rasht,
            destinationCity: null,
            departureDate: new \DateTimeImmutable('2026-10-10'),
            returnDate: new \DateTimeImmutable('2026-10-15'),
            nights: 5,
            adults: 2,
        );

        self::assertFalse($request->hasDestinationScope());
    }

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

    public function testFlexibleWindowReturnsContainedTourOptionsAndRanksLowerComparablePrice(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $planner = self::getContainer()->get(TripPlanner::class);

        [$originAirport, $destinationCity] = $this->places($em);
        $source = $this->tourSource();
        $this->externalTour($em, $source, $originAirport, $destinationCity, 'Window Match Expensive ' . self::uniqueSuffix(), '2026-09-25', '2026-09-30', 5, '900.00');
        $this->externalTour($em, $source, $originAirport, $destinationCity, 'Window Match Cheap ' . self::uniqueSuffix(), '2026-10-01', '2026-10-06', 5, '700.00');
        $this->externalTour($em, $source, $originAirport, $destinationCity, 'Before Window ' . self::uniqueSuffix(), '2026-09-20', '2026-09-25', 5, '500.00');
        $this->externalTour($em, $source, $originAirport, $destinationCity, 'Wrong Nights ' . self::uniqueSuffix(), '2026-10-02', '2026-10-08', 6, '100.00');
        $em->persist($source);
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
            departureDate: null,
            returnDate: null,
            nights: 5,
            adults: 2,
            preferredCurrency: 'EUR',
            dateMode: TripDateMode::FLEXIBLE,
            windowStart: new \DateTimeImmutable('2026-09-23'),
            windowEnd: new \DateTimeImmutable('2026-10-22'),
        ));

        self::assertSame(TripPlanStatus::OPTIONS_FOUND->value, $result->status->value);
        self::assertSame(2, $result->counts['tourOptions']);
        self::assertSame('700.00', $result->options[0]->totalPrice);
        self::assertSame('2026-10-01', $result->options[0]->departureDate->format('Y-m-d'));
        self::assertSame('2026-10-06', $result->options[0]->returnDate?->format('Y-m-d'));
        self::assertSame(5, $result->options[0]->nights);
        self::assertContains('5-night option within your requested date window', $result->options[0]->reasons);
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

        $result = $planner->plan(new TripSearchRequest(null, null, $destinationCity, new \DateTimeImmutable('2027-02-01'), new \DateTimeImmutable('2027-02-06'), 5, 2));

        self::assertSame(TripPlanStatus::NO_OPTIONS->value, $result->status->value);
        self::assertNotSame([], $result->messages);
        self::assertContains('No trip options could be built from the currently stored commercial data.', $result->messages);
    }

    public function testFlexibleNoOptionsReturnsWindowMessageAndGenerationRemainsBounded(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $planner = self::getContainer()->get(TripPlanner::class);

        [$originAirport, $destinationCity] = $this->places($em);
        $source = $this->tourSource();
        for ($index = 0; $index < 12; ++$index) {
            $departure = (new \DateTimeImmutable('2026-11-01'))->modify('+' . $index . ' days');
            $this->externalTour($em, $source, $originAirport, $destinationCity, 'Bounded ' . $index . ' ' . self::uniqueSuffix(), $departure->format('Y-m-d'), $departure->modify('+5 days')->format('Y-m-d'), 5, (string) (800 + $index) . '.00');
        }
        $em->persist($source);
        $em->flush();
        $em->clear();

        $originAirport = $em->getRepository(Airport::class)->find($originAirport->getId());
        $destinationCity = $em->getRepository(City::class)->find($destinationCity->getId());
        self::assertInstanceOf(Airport::class, $originAirport);
        self::assertInstanceOf(City::class, $destinationCity);

        $bounded = $planner->plan(new TripSearchRequest($originAirport, null, $destinationCity, null, null, 5, 2, dateMode: TripDateMode::FLEXIBLE, windowStart: new \DateTimeImmutable('2026-11-01'), windowEnd: new \DateTimeImmutable('2026-12-01')));
        self::assertSame(10, $bounded->counts['tourOptions']);
        self::assertLessThanOrEqual(5, \count($bounded->options));

        $empty = $planner->plan(new TripSearchRequest($originAirport, null, $destinationCity, null, null, 5, 2, dateMode: TripDateMode::FLEXIBLE, windowStart: new \DateTimeImmutable('2027-03-01'), windowEnd: new \DateTimeImmutable('2027-03-31')));
        self::assertSame(TripPlanStatus::NO_OPTIONS->value, $empty->status->value);
        self::assertContains('No 5-night option was found inside the selected date window.', $empty->messages);
    }

    public function testTravelPlanningServiceExecutesLiveExternalTourSearchStoresSnapshotAndUsesFreshResult(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        [$originAirport, $destinationCity] = $this->places($em);

        foreach ($em->getRepository(SearchSource::class)->findEnabledForCapability(SearchSource::CAPABILITY_TOUR) as $existingSource) {
            $existingSource->setEnabled(false);
        }

        $source = (new SearchSource())
            ->setName('Trip Planner Live Source ' . self::uniqueSuffix())
            ->setDomain('live-trip-planner.test')
            ->setProvider('live_test')
            ->setProviderType(SearchSourceProviderType::MANUAL)
            ->setCapabilities([SearchSource::CAPABILITY_TOUR])
            ->setEnabled(true);
        $em->persist($source);
        $em->flush();
        $em->clear();

        $originAirport = $em->getRepository(Airport::class)->find($originAirport->getId());
        $destinationCity = $em->getRepository(City::class)->find($destinationCity->getId());
        self::assertInstanceOf(Airport::class, $originAirport);
        self::assertInstanceOf(City::class, $destinationCity);

        $provider = new class implements ExternalTourOfferProviderInterface {
            public int $calls = 0;

            public function supports(SearchSource $source): bool
            {
                return $source->getProvider() === 'live_test';
            }

            public function search(SearchSource $source, ExternalTourOfferSearchRequest $request): ExternalTourOfferSearchResult
            {
                ++$this->calls;

                return ExternalTourOfferSearchResult::success($source, [
                    new ExternalTourOfferCandidate(
                        sourceIdentifier: ExternalTourOfferCandidate::sourceIdentifier($source),
                        sourceName: $source->getName(),
                        providerCode: 'live_test',
                        externalOfferId: 'live-istanbul-1',
                        title: 'Live Istanbul 5 Nights',
                        originText: $request->originAirport?->getIataCode(),
                        destinationText: $request->destinationCity->getName(),
                        departureDate: new \DateTimeImmutable('2026-10-01'),
                        returnDate: new \DateTimeImmutable('2026-10-06'),
                        validFrom: null,
                        validTo: null,
                        nights: 5,
                        days: 6,
                        hotelName: 'Live Hotel',
                        roomName: null,
                        boardType: null,
                        flightSummary: 'Live flight summary',
                        adults: $request->adults,
                        children: $request->children,
                        infants: $request->infants,
                        childrenAges: $request->childrenAges,
                        inclusions: ['flight', 'hotel'],
                        exclusions: [],
                        currency: 'EUR',
                        totalPrice: '700.00',
                        bookingUrl: 'https://example.test/live-istanbul',
                        availabilityStatus: TourAvailabilityStatus::AVAILABLE,
                        metadata: ['raw' => 'fixture'],
                    ),
                ], ['rawResultCount' => 1, 'rejectedCandidateCount' => 0]);
            }
        };

        $searchService = new ExternalTourOfferSearchService(
            [$provider],
            $em->getRepository(SearchSource::class),
            $container->get(TourSourceEligibilityService::class),
        );
        $planningService = new TravelPlanningService(
            $container->get(PlanningIntentClarifier::class),
            $container->get(JourneyStrategyPlanner::class),
            $searchService,
            $container->get(ExternalTourOfferStoreService::class),
            $container->get(\App\Modules\Flight\Service\FlightOfferSearchService::class),
            $container->get(\App\Modules\Flight\Service\FlightOfferStoreService::class),
            $em->getRepository(SearchSource::class),
            $em->getRepository(City::class),
            $container->get(\App\Modules\Hotel\Repository\HotelRepository::class),
            $container->get(\App\Modules\Hotel\Service\HotelOfferSearchService::class),
            $container->get(\App\Modules\Hotel\Service\HotelOfferStoreService::class),
            $container->get(\App\Modules\TripPlanner\Service\CommercialDepartureResolver::class),
            $container->get(TripPlanner::class),
        );

        $result = $planningService->plan(new TripSearchRequest(
            originAirport: $originAirport,
            originCity: null,
            destinationCity: $destinationCity,
            departureDate: null,
            returnDate: null,
            nights: 5,
            adults: 2,
            dateMode: TripDateMode::FLEXIBLE,
            windowStart: new \DateTimeImmutable('2026-09-23'),
            windowEnd: new \DateTimeImmutable('2026-12-21'),
        ));

        self::assertSame(1, $provider->calls);
        self::assertSame(TripPlanStatus::OPTIONS_FOUND->value, $result->status->value);
        self::assertSame(1, $result->diagnostics['liveSearch']['tour']['acceptedCount']);
        self::assertSame(1, $result->diagnostics['liveSearch']['tour']['storedCount']);
        self::assertSame('live_external', $result->options[0]->sourceType);
        self::assertSame('Live Istanbul 5 Nights', $result->options[0]->title);
        self::assertContains('Fresh live external result', $result->options[0]->reasons);

        $stored = $em->getRepository(ExternalTourOffer::class)->findOneBy(['externalOfferId' => 'live-istanbul-1']);
        self::assertInstanceOf(ExternalTourOffer::class, $stored);
        self::assertSame('700.00', $stored->getTotalPrice());
    }

    public function testDestinationFreeCheapestIntentCanStartWithoutDestination(): void
    {
        $request = new TripSearchRequest(
            originAirport: null,
            originCity: null,
            destinationCity: null,
            departureDate: null,
            returnDate: null,
            nights: 5,
            adults: 2,
            dateMode: TripDateMode::FLEXIBLE,
            windowStart: new \DateTimeImmutable('2026-09-23'),
            windowEnd: new \DateTimeImmutable('2026-12-21'),
            goal: TripPlanningGoal::CHEAPEST,
        );

        self::assertFalse($request->requiresSpecificDestination());
        $clarification = (new PlanningIntentClarifier())->evaluate($request);
        self::assertTrue($clarification->isReady());
    }

    public function testCountryOnlyDestinationDiscoversConfiguredTourCitiesAndUsesLiveResults(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        [$originAirport, $destinationCity] = $this->places($em);
        $em->flush();
        $country = $destinationCity->getCountry();
        self::assertInstanceOf(Country::class, $country);

        foreach ($em->getRepository(SearchSource::class)->findEnabledForCapability(SearchSource::CAPABILITY_TOUR) as $existingSource) {
            $existingSource->setEnabled(false);
        }

        $source = (new SearchSource())
            ->setName('Country Discovery Source ' . self::uniqueSuffix())
            ->setDomain('country-trip-planner.test')
            ->setProvider('country_live_test')
            ->setProviderType(SearchSourceProviderType::MANUAL)
            ->setCapabilities([SearchSource::CAPABILITY_TOUR])
            ->setConfig([
                'supportedOriginCityIds' => [(int) $originAirport->getCity()?->getId()],
                'supportedDestinationCityIds' => [(int) $destinationCity->getId()],
            ])
            ->setEnabled(true);
        $em->persist($source);
        $em->flush();
        $em->clear();

        $originAirport = $em->getRepository(Airport::class)->find($originAirport->getId());
        $country = $em->getRepository(Country::class)->find($country->getId());
        self::assertInstanceOf(Airport::class, $originAirport);
        self::assertInstanceOf(Country::class, $country);

        $provider = new class implements ExternalTourOfferProviderInterface {
            public int $calls = 0;

            public function supports(SearchSource $source): bool
            {
                return $source->getProvider() === 'country_live_test';
            }

            public function search(SearchSource $source, ExternalTourOfferSearchRequest $request): ExternalTourOfferSearchResult
            {
                ++$this->calls;

                return ExternalTourOfferSearchResult::success($source, [
                    new ExternalTourOfferCandidate(
                        sourceIdentifier: ExternalTourOfferCandidate::sourceIdentifier($source),
                        sourceName: $source->getName(),
                        providerCode: 'country_live_test',
                        externalOfferId: 'country-live-' . $request->destinationCity->getId(),
                        title: 'Country Live Tour',
                        originText: $request->originAirport?->getIataCode(),
                        destinationText: $request->destinationCity->getName(),
                        departureDate: new \DateTimeImmutable('2026-10-01'),
                        returnDate: new \DateTimeImmutable('2026-10-06'),
                        validFrom: null,
                        validTo: null,
                        nights: 5,
                        days: 6,
                        hotelName: 'Country Hotel',
                        roomName: null,
                        boardType: null,
                        flightSummary: 'Country airline',
                        adults: $request->adults,
                        children: $request->children,
                        infants: $request->infants,
                        childrenAges: $request->childrenAges,
                        inclusions: ['flight', 'hotel'],
                        exclusions: [],
                        currency: 'EUR',
                        totalPrice: '640.00',
                        bookingUrl: 'https://example.test/country-live',
                        availabilityStatus: TourAvailabilityStatus::AVAILABLE,
                        metadata: ['hotelStars' => 4],
                    ),
                ], ['rawResultCount' => 1, 'rejectedCandidateCount' => 0]);
            }
        };

        $planningService = new TravelPlanningService(
            $container->get(PlanningIntentClarifier::class),
            $container->get(JourneyStrategyPlanner::class),
            new ExternalTourOfferSearchService([$provider], $em->getRepository(SearchSource::class), $container->get(TourSourceEligibilityService::class)),
            $container->get(ExternalTourOfferStoreService::class),
            $container->get(\App\Modules\Flight\Service\FlightOfferSearchService::class),
            $container->get(\App\Modules\Flight\Service\FlightOfferStoreService::class),
            $em->getRepository(SearchSource::class),
            $em->getRepository(City::class),
            $container->get(\App\Modules\Hotel\Repository\HotelRepository::class),
            $container->get(\App\Modules\Hotel\Service\HotelOfferSearchService::class),
            $container->get(\App\Modules\Hotel\Service\HotelOfferStoreService::class),
            $container->get(\App\Modules\TripPlanner\Service\CommercialDepartureResolver::class),
            $container->get(TripPlanner::class),
        );

        $result = $planningService->plan(new TripSearchRequest(
            originAirport: $originAirport,
            originCity: null,
            destinationCity: null,
            departureDate: null,
            returnDate: null,
            nights: 5,
            adults: 2,
            preferredCurrency: 'EUR',
            dateMode: TripDateMode::FLEXIBLE,
            windowStart: new \DateTimeImmutable('2026-09-23'),
            windowEnd: new \DateTimeImmutable('2026-10-22'),
            destinationCountry: $country,
        ));

        self::assertSame(1, $provider->calls);
        self::assertSame(TripPlanStatus::OPTIONS_FOUND->value, $result->status->value);
        self::assertSame(1, $result->diagnostics['liveSearch']['tour']['searches']);
        self::assertSame([$result->options[0]->destinationCity], $result->diagnostics['liveSearch']['tour']['countryDiscovery']['cities']);
        self::assertSame($country->getName(), $result->options[0]->destinationCountry);
        self::assertSame('Country Live Tour', $result->options[0]->title);
    }

    public function testHotelRecommendationContextUsesFactualExternalMetadataOnly(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $planner = self::getContainer()->get(TripPlanner::class);
        [$originAirport, $destinationCity] = $this->places($em);
        $source = $this->tourSource();
        $this->externalTour($em, $source, $originAirport, $destinationCity, 'Hotel Context Tour ' . self::uniqueSuffix(), '2026-10-01', '2026-10-06', 5, '750.00', ['hotelName' => 'Source Only Hotel', 'hotelStars' => 4]);
        $em->persist($source);
        $em->flush();
        $em->clear();

        $originAirport = $em->getRepository(Airport::class)->find($originAirport->getId());
        $destinationCity = $em->getRepository(City::class)->find($destinationCity->getId());
        self::assertInstanceOf(Airport::class, $originAirport);
        self::assertInstanceOf(City::class, $destinationCity);

        $result = $planner->plan(new TripSearchRequest($originAirport, null, $destinationCity, new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-06'), 5, 2));

        self::assertNotNull($result->options[0]->hotelRecommendationContext);
        self::assertFalse($result->options[0]->hotelRecommendationContext->canonicalMatched);
        self::assertSame(4, $result->options[0]->hotelRecommendationContext->stars);
        self::assertContains('No hotel review/source context is available.', $result->options[0]->hotelRecommendationContext->warnings);
    }

    public function testHotelRecommendationContextUsesSourceReviewFactsWhenPresent(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $planner = self::getContainer()->get(TripPlanner::class);
        [$originAirport, $destinationCity] = $this->places($em);
        $source = $this->tourSource();
        $this->externalTour($em, $source, $originAirport, $destinationCity, 'Hotel Review Context Tour ' . self::uniqueSuffix(), '2026-10-01', '2026-10-06', 5, '750.00', [
            'hotelName' => 'Reviewed Source Hotel',
            'rawProviderOffer' => [
                'hotels' => [
                    [
                        'hotel' => [
                            'titleEn' => 'Reviewed Source Hotel',
                            'averageReviewScore' => 4.32,
                            'reviewsCount' => 36,
                        ],
                    ],
                ],
            ],
        ]);
        $em->persist($source);
        $em->flush();
        $em->clear();

        $originAirport = $em->getRepository(Airport::class)->find($originAirport->getId());
        $destinationCity = $em->getRepository(City::class)->find($destinationCity->getId());
        self::assertInstanceOf(Airport::class, $originAirport);
        self::assertInstanceOf(City::class, $destinationCity);

        $result = $planner->plan(new TripSearchRequest($originAirport, null, $destinationCity, new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-06'), 5, 2));

        self::assertNotNull($result->options[0]->hotelRecommendationContext);
        self::assertSame('4.32', $result->options[0]->hotelRecommendationContext->sourceReviewScore);
        self::assertSame(36, $result->options[0]->hotelRecommendationContext->sourceReviewCount);
        self::assertNotContains('No hotel review/source context is available.', $result->options[0]->hotelRecommendationContext->warnings);
    }

    public function testHotelRecommendationContextMatchesCanonicalHotelAndUsesFreshSourceReference(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $planner = self::getContainer()->get(TripPlanner::class);
        [$originAirport, $destinationCity] = $this->places($em);
        $hotel = (new Hotel())
            ->setCity($destinationCity)
            ->setName('Exact Context Hotel ' . self::uniqueSuffix())
            ->setSlug('exact-context-hotel-' . strtolower(self::uniqueSuffix()))
            ->setStars(5)
            ->setActive(true)
            ->setVerified(true);
        $reference = (new HotelSourceReference())
            ->setHotel($hotel)
            ->setSource('booking')
            ->setExternalId('booking-' . self::uniqueSuffix())
            ->setSourceUrl('https://booking.example.test/hotel/exact-context')
            ->setSourceTitle($hotel->getName())
            ->setLastSyncedAt(new \DateTimeImmutable('-1 day'))
            ->setMetadata([
                'recommendationContext' => [
                    'status' => 'SUCCESS',
                    'fetchedAt' => (new \DateTimeImmutable('-1 day'))->format(DATE_ATOM),
                    'reviewScore' => '8.5',
                    'reviewScoreScale' => '10',
                    'reviewCount' => 1234,
                ],
            ]);
        $hotel->addSourceReference($reference);
        $em->persist($hotel);
        $em->persist($reference);

        $source = $this->tourSource();
        $this->externalTour($em, $source, $originAirport, $destinationCity, 'Exact Hotel Context Tour ' . self::uniqueSuffix(), '2026-10-01', '2026-10-06', 5, '750.00', ['hotelName' => $hotel->getName()]);
        $em->persist($source);
        $em->flush();
        $em->clear();

        $originAirport = $em->getRepository(Airport::class)->find($originAirport->getId());
        $destinationCity = $em->getRepository(City::class)->find($destinationCity->getId());
        self::assertInstanceOf(Airport::class, $originAirport);
        self::assertInstanceOf(City::class, $destinationCity);

        $result = $planner->plan(new TripSearchRequest($originAirport, null, $destinationCity, new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-06'), 5, 2));
        $context = $result->options[0]->hotelRecommendationContext;

        self::assertNotNull($context);
        self::assertTrue($context->canonicalMatched);
        self::assertSame('matched', $context->canonicalMatchStatus);
        self::assertSame('8.5', $context->sourceReviewScore);
        self::assertSame(1234, $context->sourceReviewCount);
        $booking = array_values(array_filter($context->sources, static fn ($source): bool => $source->source === 'booking'))[0] ?? null;
        self::assertNotNull($booking);
        self::assertSame('fresh', $booking->freshness);
        self::assertFalse($booking->liveRefreshAttempted);
    }

    public function testHotelRecommendationContextKeepsAmbiguousCanonicalMatchUnselected(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $planner = self::getContainer()->get(TripPlanner::class);
        [$originAirport, $destinationCity] = $this->places($em);
        $name = 'Ambiguous Context Hotel ' . self::uniqueSuffix();
        for ($index = 0; $index < 2; ++$index) {
            $em->persist((new Hotel())
                ->setCity($destinationCity)
                ->setName($name)
                ->setSlug('ambiguous-context-hotel-' . strtolower(self::uniqueSuffix()))
                ->setActive(true));
        }
        $source = $this->tourSource();
        $this->externalTour($em, $source, $originAirport, $destinationCity, 'Ambiguous Hotel Context Tour ' . self::uniqueSuffix(), '2026-10-01', '2026-10-06', 5, '750.00', ['hotelName' => $name]);
        $em->persist($source);
        $em->flush();
        $em->clear();

        $originAirport = $em->getRepository(Airport::class)->find($originAirport->getId());
        $destinationCity = $em->getRepository(City::class)->find($destinationCity->getId());
        self::assertInstanceOf(Airport::class, $originAirport);
        self::assertInstanceOf(City::class, $destinationCity);

        $result = $planner->plan(new TripSearchRequest($originAirport, null, $destinationCity, new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-06'), 5, 2));
        $context = $result->options[0]->hotelRecommendationContext;

        self::assertNotNull($context);
        self::assertFalse($context->canonicalMatched);
        self::assertSame('ambiguous', $context->canonicalMatchStatus);
        self::assertContains('Canonical hotel match is ambiguous; no hotel was selected automatically.', $context->warnings);
    }

    public function testStaleHotelSourceReferenceIsMarkedAsCachedFallbackWhenLiveRefreshCannotRun(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $planner = self::getContainer()->get(TripPlanner::class);
        [$originAirport, $destinationCity] = $this->places($em);
        $hotel = (new Hotel())
            ->setCity($destinationCity)
            ->setName('Stale Context Hotel ' . self::uniqueSuffix())
            ->setSlug('stale-context-hotel-' . strtolower(self::uniqueSuffix()))
            ->setActive(true);
        $reference = (new HotelSourceReference())
            ->setHotel($hotel)
            ->setSource('tripadvisor')
            ->setExternalId('tripadvisor-' . self::uniqueSuffix())
            ->setLastSyncedAt(new \DateTimeImmutable('-30 days'))
            ->setMetadata([
                'recommendationContext' => [
                    'status' => 'SUCCESS',
                    'fetchedAt' => (new \DateTimeImmutable('-30 days'))->format(DATE_ATOM),
                    'reviewScore' => '4.1',
                    'reviewScoreScale' => '5',
                    'reviewCount' => 88,
                ],
            ]);
        $hotel->addSourceReference($reference);
        $em->persist($hotel);
        $em->persist($reference);
        $source = $this->tourSource();
        $this->externalTour($em, $source, $originAirport, $destinationCity, 'Stale Hotel Context Tour ' . self::uniqueSuffix(), '2026-10-01', '2026-10-06', 5, '750.00', ['hotelName' => $hotel->getName()]);
        $em->persist($source);
        $em->flush();
        $em->clear();

        $originAirport = $em->getRepository(Airport::class)->find($originAirport->getId());
        $destinationCity = $em->getRepository(City::class)->find($destinationCity->getId());
        self::assertInstanceOf(Airport::class, $originAirport);
        self::assertInstanceOf(City::class, $destinationCity);

        $result = $planner->plan(new TripSearchRequest($originAirport, null, $destinationCity, new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-06'), 5, 2));
        $context = $result->options[0]->hotelRecommendationContext;

        self::assertNotNull($context);
        self::assertSame('matched', $context->canonicalMatchStatus);
        $tripadvisor = array_values(array_filter($context->sources, static fn ($source): bool => $source->source === 'tripadvisor'))[0] ?? null;
        self::assertNotNull($tripadvisor);
        self::assertSame('cached_fallback', $tripadvisor->status);
        self::assertSame('stale', $tripadvisor->freshness);
        self::assertSame('missing_source_url', $tripadvisor->liveRefreshStatus);
        self::assertSame('4.1', $tripadvisor->reviewScore);
    }

    public function testHotelRecommendationEnrichmentIsBoundedToFiveShortlistedHotels(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $planner = self::getContainer()->get(TripPlanner::class);
        [$originAirport, $destinationCity] = $this->places($em);
        $source = $this->tourSource();
        for ($index = 0; $index < 7; ++$index) {
            $departure = (new \DateTimeImmutable('2026-10-01'))->modify('+' . $index . ' days');
            $this->externalTour(
                $em,
                $source,
                $originAirport,
                $destinationCity,
                'Bounded Hotel Context Tour ' . $index . ' ' . self::uniqueSuffix(),
                $departure->format('Y-m-d'),
                $departure->modify('+5 days')->format('Y-m-d'),
                5,
                (string) (700 + $index) . '.00',
                ['hotelName' => 'Bounded Context Hotel ' . $index . ' ' . self::uniqueSuffix()]
            );
        }
        $em->persist($source);
        $em->flush();
        $em->clear();

        $originAirport = $em->getRepository(Airport::class)->find($originAirport->getId());
        $destinationCity = $em->getRepository(City::class)->find($destinationCity->getId());
        self::assertInstanceOf(Airport::class, $originAirport);
        self::assertInstanceOf(City::class, $destinationCity);

        $result = $planner->plan(new TripSearchRequest($originAirport, null, $destinationCity, null, null, 5, 2, dateMode: TripDateMode::FLEXIBLE, windowStart: new \DateTimeImmutable('2026-10-01'), windowEnd: new \DateTimeImmutable('2026-10-31')));

        self::assertLessThanOrEqual(5, \count($result->options));
        self::assertSame(5, $result->diagnostics['hotelRecommendationContext']['attemptedHotels']);
    }

    public function testHotelRecommendationEnrichmentDeduplicatesRepeatedHotelAcrossOptions(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $planner = self::getContainer()->get(TripPlanner::class);
        [$originAirport, $destinationCity] = $this->places($em);
        $source = $this->tourSource();
        $sharedHotelName = 'Shared Duplicate Hotel ' . self::uniqueSuffix();
        for ($index = 0; $index < 3; ++$index) {
            $departure = (new \DateTimeImmutable('2026-10-01'))->modify('+' . $index . ' days');
            $this->externalTour(
                $em,
                $source,
                $originAirport,
                $destinationCity,
                'Duplicate Hotel Tour ' . $index . ' ' . self::uniqueSuffix(),
                $departure->format('Y-m-d'),
                $departure->modify('+5 days')->format('Y-m-d'),
                5,
                (string) (700 + $index) . '.00',
                ['hotelName' => $sharedHotelName]
            );
        }
        $em->persist($source);
        $em->flush();
        $em->clear();

        $originAirport = $em->getRepository(Airport::class)->find($originAirport->getId());
        $destinationCity = $em->getRepository(City::class)->find($destinationCity->getId());
        self::assertInstanceOf(Airport::class, $originAirport);
        self::assertInstanceOf(City::class, $destinationCity);

        $result = $planner->plan(new TripSearchRequest($originAirport, null, $destinationCity, null, null, 5, 2, dateMode: TripDateMode::FLEXIBLE, windowStart: new \DateTimeImmutable('2026-10-01'), windowEnd: new \DateTimeImmutable('2026-10-31')));

        $matchingOptions = array_values(array_filter($result->options, static fn ($option): bool => $option->hotelName === $sharedHotelName));
        self::assertGreaterThanOrEqual(2, \count($matchingOptions), 'Expected the shared hotel to appear on multiple shortlisted options.');
        self::assertSame(1, $result->diagnostics['hotelRecommendationContext']['attemptedHotels']);
        self::assertSame($matchingOptions[0]->hotelRecommendationContext, $matchingOptions[1]->hotelRecommendationContext);
    }

    public function testJourneyStrategyRepresentsHubRouteWhenDirectInventoryIsUnavailable(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $planner = self::getContainer()->get(JourneyStrategyPlanner::class);
        [$originAirport, $destinationCity] = $this->places($em);

        $hubCity = (new City())->setCountry($originAirport->getCity()?->getCountry())->setName('Hub City ' . self::uniqueSuffix())->setSlug('hub-city-' . strtolower(self::uniqueSuffix()));
        $hubAirport = (new Airport())->setCity($hubCity)->setName('Hub Airport ' . self::uniqueSuffix())->setIataCode($this->uniqueIata($em))->setActive(true);
        $em->persist($hubCity);
        $em->persist($hubAirport);
        $em->flush();

        $strategies = $planner->strategies(new TripSearchRequest(
            originAirport: $originAirport,
            originCity: null,
            destinationCity: $destinationCity,
            departureDate: new \DateTimeImmutable('2026-10-01'),
            returnDate: new \DateTimeImmutable('2026-10-06'),
            nights: 5,
            adults: 2,
        ));

        self::assertNotEmpty($strategies);
        self::assertTrue((bool) array_filter($strategies, static fn ($strategy): bool => \count($strategy->legs) >= 2));
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

    private function tourSource(): SearchSource
    {
        return (new SearchSource())
            ->setName('Trip Planner Tour Source ' . self::uniqueSuffix())
            ->setDomain('example.test')
            ->setProvider('test')
            ->setProviderType(SearchSourceProviderType::MANUAL)
            ->setCapabilities([SearchSource::CAPABILITY_TOUR])
            ->setEnabled(true);
    }

    private function externalTour(EntityManagerInterface $em, SearchSource $source, Airport $origin, City $destination, string $title, string $departure, string $return, int $nights, string $price, array $metadata = []): void
    {
        $hotelName = \is_string($metadata['hotelName'] ?? null) ? $metadata['hotelName'] : null;
        $offer = (new ExternalTourOffer())
            ->setSearchSource($source)
            ->setProviderCode('test')
            ->setTitle($title)
            ->setOriginAirport($origin)
            ->setOriginText((string) $origin->getCity()?->getName())
            ->setDestinationCity($destination)
            ->setDestinationText($destination->getName())
            ->setDepartureDate(new \DateTimeImmutable($departure))
            ->setReturnDate(new \DateTimeImmutable($return))
            ->setNights($nights)
            ->setHotelName($hotelName)
            ->setAdults(2)
            ->setChildren(0)
            ->setInfants(0)
            ->setCurrency('EUR')
            ->setTotalPrice($price)
            ->setAvailabilityStatus(TourAvailabilityStatus::AVAILABLE)
            ->setFetchedAt(new \DateTimeImmutable('-1 hour'))
            ->setExpiresAt(new \DateTimeImmutable('+5 hours'))
            ->setMetadata($metadata);

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
