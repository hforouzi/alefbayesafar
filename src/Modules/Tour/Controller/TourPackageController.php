<?php

namespace App\Modules\Tour\Controller;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Enum\FlightPriceSourceType;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelRoomType;
use App\Modules\Tour\Entity\TourPackage;
use App\Modules\Tour\Enum\TourPricingMode;
use App\Modules\Tour\Form\TourPackageType;
use App\Modules\Tour\Repository\TourPackageRepository;
use App\Modules\Tour\Service\TourAdminListRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/tour-commerce/packages')]
class TourPackageController extends AbstractController
{
    #[Route('/', name: 'tour_package_index', methods: ['GET'])]
    public function index(Request $request, TourPackageRepository $packageRepository, TourAdminListRequest $adminListRequest): Response
    {
        $filters = $adminListRequest->packageFilters($request);
        $packages = $packageRepository->findForAdminPage($filters);

        return $this->render('@Tour/package/index.html.twig', [
            'packages' => $packages->items,
            'pagination' => $packages,
        ]);
    }

    #[Route('/new', name: 'tour_package_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $package = new TourPackage();
        $form = $this->createForm(TourPackageType::class, $package);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->applyUnmappedFields($form, $package, $entityManager);
            $this->normalizeUnusedPrices($package);
            $this->validatePackage($form, $package, $validator);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($package);
            $entityManager->flush();

            $this->addFlash('success', 'tour.package.flash.created');

            return $this->redirectToRoute('tour_package_index');
        }

        return $this->render('@Tour/package/new.html.twig', [
            'package' => $package,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'tour_package_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, TourPackage $package, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $form = $this->createForm(TourPackageType::class, $package);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->applyUnmappedFields($form, $package, $entityManager);
            $this->normalizeUnusedPrices($package);
            $this->validatePackage($form, $package, $validator);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'tour.package.flash.updated');

            return $this->redirectToRoute('tour_package_index');
        }

        return $this->render('@Tour/package/edit.html.twig', [
            'package' => $package,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/toggle', name: 'tour_package_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Request $request, TourPackage $package, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle_tour_package_' . $package->getId(), (string) $request->request->get('_token'))) {
            $package->setActive(!$package->isActive());
            $entityManager->flush();
        }

        return $this->redirectToRoute('tour_package_index');
    }

    private function applyUnmappedFields(FormInterface $form, TourPackage $package, EntityManagerInterface $entityManager): void
    {
        $originAirport = $this->optionalEntity($form, 'originAirportId', Airport::class, $entityManager);
        $package->setOriginAirport($originAirport instanceof Airport ? $originAirport : null);
        $destinationCity = $this->requiredEntity($form, 'destinationCityId', City::class, $entityManager, 'tour.package.validation.destination_required');
        if ($destinationCity instanceof City) {
            $package->setDestinationCity($destinationCity);
        }

        $flightOffer = $this->optionalEntity($form, 'flightOfferId', FlightOffer::class, $entityManager);
        if ($flightOffer instanceof FlightOffer && $flightOffer->getSourceType() !== FlightPriceSourceType::OWN) {
            $form->get('flightOfferId')->addError(new FormError('tour.package.validation.flight_offer_own'));
        }
        $package->setFlightOffer($flightOffer instanceof FlightOffer ? $flightOffer : null);

        $hotel = $this->optionalEntity($form, 'hotelId', Hotel::class, $entityManager);
        $roomType = $this->optionalEntity($form, 'hotelRoomTypeId', HotelRoomType::class, $entityManager);
        $package->setHotel($hotel instanceof Hotel ? $hotel : null);
        $package->setHotelRoomType($roomType instanceof HotelRoomType ? $roomType : null);

        if ($roomType instanceof HotelRoomType && !$hotel instanceof Hotel) {
            $form->get('hotelId')->addError(new FormError('tour.package.validation.hotel_required_for_room'));
        }
        if ($hotel instanceof Hotel && $roomType instanceof HotelRoomType && $roomType->getHotel() !== $hotel) {
            $form->get('hotelRoomTypeId')->addError(new FormError('tour.package.validation.room_type_hotel'));
        }

        $this->applyChildrenAges($form, $package);
    }

    /**
     * @param class-string $class
     */
    private function optionalEntity(FormInterface $form, string $field, string $class, EntityManagerInterface $entityManager): ?object
    {
        $value = (string) $form->get($field)->getData();
        if ($value === '') {
            return null;
        }

        if (!ctype_digit($value)) {
            $form->get($field)->addError(new FormError('tour.package.validation.invalid_reference'));

            return null;
        }

        $entity = $entityManager->getRepository($class)->find((int) $value);
        if (!\is_object($entity)) {
            $form->get($field)->addError(new FormError('tour.package.validation.invalid_reference'));
        }

        return \is_object($entity) ? $entity : null;
    }

    /**
     * @param class-string $class
     */
    private function requiredEntity(FormInterface $form, string $field, string $class, EntityManagerInterface $entityManager, string $message): ?object
    {
        $entity = $this->optionalEntity($form, $field, $class, $entityManager);
        if (!$entity instanceof $class) {
            $form->get($field)->addError(new FormError($message));
        }

        return $entity;
    }

    private function applyChildrenAges(FormInterface $form, TourPackage $package): void
    {
        $raw = trim((string) $form->get('childrenAgesText')->getData());
        if ($package->getChildren() === 0) {
            $package->setChildrenAges([]);

            return;
        }

        if ($raw === '') {
            $package->setChildrenAges([]);

            return;
        }

        $parts = preg_split('/[,\s]+/', $raw) ?: [];
        $ages = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (!ctype_digit($part)) {
                $form->get('childrenAgesText')->addError(new FormError('tour.package.validation.child_ages_numeric'));

                return;
            }
            $ages[] = (int) $part;
        }

        $package->setChildrenAges($ages);
    }

    private function normalizeUnusedPrices(TourPackage $package): void
    {
        if ($package->getPricingMode() === TourPricingMode::TOTAL_PARTY) {
            $package
                ->setAdultPrice(null)
                ->setChildPrice(null)
                ->setInfantPrice(null);

            return;
        }

        $package->setTotalPrice(null);
    }

    private function validatePackage(FormInterface $form, TourPackage $package, ValidatorInterface $validator): void
    {
        foreach ($validator->validate($package) as $violation) {
            $field = $this->formFieldForViolationPath($violation->getPropertyPath());
            if ($form->has($field)) {
                $form->get($field)->addError(new FormError((string) $violation->getMessage()));

                continue;
            }

            $form->addError(new FormError((string) $violation->getMessage()));
        }
    }

    private function formFieldForViolationPath(string $propertyPath): string
    {
        return match ($propertyPath) {
            'originAirport' => 'originAirportId',
            'destinationCity' => 'destinationCityId',
            'flightOffer' => 'flightOfferId',
            'hotel' => 'hotelId',
            'hotelRoomType' => 'hotelRoomTypeId',
            'childrenAges' => 'childrenAgesText',
            default => $propertyPath,
        };
    }
}
