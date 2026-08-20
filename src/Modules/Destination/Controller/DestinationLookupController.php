<?php

namespace App\Modules\Destination\Controller;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\Destination\Entity\State;
use App\Modules\Destination\Repository\AirportRepository;
use App\Modules\Destination\Repository\CityRepository;
use App\Modules\Destination\Repository\CountryRepository;
use App\Modules\Destination\Repository\DistrictRepository;
use App\Modules\Destination\Repository\StateRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/catalog/lookup')]
class DestinationLookupController extends AbstractController
{
    private const LIMIT = 25;

    #[Route('/countries', name: 'destination_lookup_countries', methods: ['GET'])]
    public function countries(Request $request, CountryRepository $countryRepository): JsonResponse
    {
        return $this->json(['results' => array_map(
            static fn (Country $country): array => [
                'id' => $country->getId(),
                'text' => sprintf('%s%s', $country->getName(), $country->getIso2() !== null ? ' - ' . $country->getIso2() : ''),
            ],
            $countryRepository->search($this->query($request), self::LIMIT)
        )]);
    }

    #[Route('/states', name: 'destination_lookup_states', methods: ['GET'])]
    public function states(Request $request, StateRepository $stateRepository, CountryRepository $countryRepository): JsonResponse
    {
        $country = $this->entity($countryRepository, $request->query->getInt('country'));

        return $this->json(['results' => array_map(
            static fn (State $state): array => [
                'id' => $state->getId(),
                'text' => sprintf('%s - %s', $state->getName(), $state->getCountry()?->getName() ?? ''),
            ],
            $stateRepository->search($this->query($request), $country, self::LIMIT)
        )]);
    }

    #[Route('/cities', name: 'destination_lookup_cities', methods: ['GET'])]
    public function cities(Request $request, CityRepository $cityRepository, CountryRepository $countryRepository, StateRepository $stateRepository): JsonResponse
    {
        $country = $this->entity($countryRepository, $request->query->getInt('country'));
        $state = $this->entity($stateRepository, $request->query->getInt('state'));

        return $this->json(['results' => array_map(
            static fn (City $city): array => [
                'id' => $city->getId(),
                'text' => sprintf('%s - %s%s', $city->getName(), $city->getState() instanceof State ? $city->getState()->getName() . ' - ' : '', $city->getCountry()?->getName() ?? ''),
            ],
            $cityRepository->search($this->query($request), $country, $state, self::LIMIT)
        )]);
    }

    #[Route('/districts', name: 'destination_lookup_districts', methods: ['GET'])]
    public function districts(Request $request, DistrictRepository $districtRepository, CityRepository $cityRepository): JsonResponse
    {
        $city = $this->entity($cityRepository, $request->query->getInt('city'));

        return $this->json(['results' => array_map(
            static fn ($district): array => [
                'id' => $district->getId(),
                'text' => sprintf('%s - %s', $district->getName(), $district->getCity()?->getName() ?? ''),
            ],
            $districtRepository->search($this->query($request), $city, self::LIMIT)
        )]);
    }

    #[Route('/airports', name: 'destination_lookup_airports', methods: ['GET'])]
    public function airports(Request $request, AirportRepository $airportRepository, CityRepository $cityRepository): JsonResponse
    {
        $city = $this->entity($cityRepository, $request->query->getInt('city'));

        return $this->json(['results' => array_map(
            static fn ($airport): array => [
                'id' => $airport->getId(),
                'text' => sprintf('%s%s - %s', $airport->getIataCode() !== null ? $airport->getIataCode() . ' - ' : '', $airport->getName(), $airport->getCity()?->getName() ?? ''),
            ],
            $airportRepository->search($this->query($request), $city, self::LIMIT)
        )]);
    }

    private function query(Request $request): string
    {
        return mb_substr(trim((string) $request->query->get('q', '')), 0, 80);
    }

    private function entity(object $repository, int $id): ?object
    {
        if ($id <= 0 || !method_exists($repository, 'find')) {
            return null;
        }

        $entity = $repository->find($id);

        return \is_object($entity) ? $entity : null;
    }
}
