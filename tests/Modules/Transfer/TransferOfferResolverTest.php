<?php

namespace App\Tests\Modules\Transfer;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Transfer\Entity\TransferOffer;
use App\Modules\Transfer\Entity\TransferProduct;
use App\Modules\Transfer\Enum\TransferPricingMode;
use App\Modules\Transfer\Service\TransferOfferResolver;
use App\Modules\Transfer\ValueObject\TransferEndpointContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TransferOfferResolverTest extends KernelTestCase
{
    public function testResolverReturnsMatchingActiveOwnOffersRankedByPriorityThenPrice(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $resolver = new TransferOfferResolver(
            $em->getRepository(TransferProduct::class),
            $em->getRepository(TransferOffer::class),
        );

        $suffix = self::uniqueSuffix();
        $country = (new Country())->setName('Transfer Resolver Country ' . $suffix);
        $city = (new City())->setCountry($country)->setName('Transfer Resolver City ' . $suffix)->setSlug('transfer-resolver-city-' . strtolower($suffix));
        $airport = (new Airport())->setCity($city)->setName('Resolver Airport ' . $suffix)->setIataCode(substr($suffix, 0, 3));
        $hotel = (new Hotel())->setCity($city)->setName('Resolver Hotel ' . $suffix)->setSlug('resolver-hotel-' . strtolower($suffix));

        $em->persist($country);
        $em->persist($city);
        $em->persist($airport);
        $em->persist($hotel);

        $cheaperLowPriority = $this->product($airport, $hotel, 'Sedan Transfer ' . $suffix, 50, '25.00', 3);
        $costlierHighPriority = $this->product($airport, $hotel, 'Van Transfer ' . $suffix, 150, '40.00', 6);
        $inactiveProduct = $this->product($airport, $hotel, 'Inactive Transfer ' . $suffix, 200, '5.00', 3);
        $inactiveProduct->setActive(false);

        foreach ([$cheaperLowPriority, $costlierHighPriority, $inactiveProduct] as $product) {
            $em->persist($product);
            foreach ($product->getOffers() as $offer) {
                $em->persist($offer);
            }
        }
        $em->flush();
        $em->clear();

        $reloadedAirport = $em->getRepository(Airport::class)->find($airport->getId());
        $reloadedHotel = $em->getRepository(Hotel::class)->find($hotel->getId());
        self::assertInstanceOf(Airport::class, $reloadedAirport);
        self::assertInstanceOf(Hotel::class, $reloadedHotel);

        $candidates = $resolver->resolve(
            TransferEndpointContext::forAirport($reloadedAirport),
            TransferEndpointContext::forHotel($reloadedHotel),
            new \DateTimeImmutable('2026-10-10'),
            2,
        );

        self::assertCount(2, $candidates);
        self::assertSame('Van Transfer ' . $suffix, $candidates[0]->transferProduct->getName());
        self::assertSame('Sedan Transfer ' . $suffix, $candidates[1]->transferProduct->getName());
    }

    public function testResolverExcludesProductsExceedingMaxPassengers(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $resolver = new TransferOfferResolver(
            $em->getRepository(TransferProduct::class),
            $em->getRepository(TransferOffer::class),
        );

        $suffix = self::uniqueSuffix();
        $country = (new Country())->setName('Transfer Passenger Country ' . $suffix);
        $city = (new City())->setCountry($country)->setName('Transfer Passenger City ' . $suffix)->setSlug('transfer-passenger-city-' . strtolower($suffix));
        $airport = (new Airport())->setCity($city)->setName('Passenger Airport ' . $suffix)->setIataCode(substr($suffix, 0, 3));
        $hotel = (new Hotel())->setCity($city)->setName('Passenger Hotel ' . $suffix)->setSlug('passenger-hotel-' . strtolower($suffix));

        $em->persist($country);
        $em->persist($city);
        $em->persist($airport);
        $em->persist($hotel);

        $sedan = $this->product($airport, $hotel, 'Small Sedan ' . $suffix, 100, '20.00', 2);
        $em->persist($sedan);
        foreach ($sedan->getOffers() as $offer) {
            $em->persist($offer);
        }
        $em->flush();
        $em->clear();

        $reloadedAirport = $em->getRepository(Airport::class)->find($airport->getId());
        $reloadedHotel = $em->getRepository(Hotel::class)->find($hotel->getId());
        self::assertInstanceOf(Airport::class, $reloadedAirport);
        self::assertInstanceOf(Hotel::class, $reloadedHotel);

        $candidates = $resolver->resolve(
            TransferEndpointContext::forAirport($reloadedAirport),
            TransferEndpointContext::forHotel($reloadedHotel),
            new \DateTimeImmutable('2026-10-10'),
            5,
        );

        self::assertSame([], $candidates);
    }

    private function product(Airport $airport, Hotel $hotel, string $name, int $priority, string $totalPrice, int $maxPassengers): TransferProduct
    {
        $product = (new TransferProduct())
            ->setOriginAirport($airport)
            ->setDestinationHotel($hotel)
            ->setName($name)
            ->setTransferType('private')
            ->setVehicleType('sedan')
            ->setMaxPassengers($maxPassengers)
            ->setActive(true);

        $offer = (new TransferOffer())
            ->setTransferProduct($product)
            ->setCurrency('EUR')
            ->setPricingMode(TransferPricingMode::TOTAL_SERVICE)
            ->setTotalPrice($totalPrice)
            ->setPriority($priority)
            ->setActive(true);
        $product->addOffer($offer);

        return $product;
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
