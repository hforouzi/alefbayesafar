<?php

namespace App\Modules\Flight\Controller;

use App\Modules\Flight\Entity\Airline;
use App\Modules\Flight\Repository\AirlineRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/flight-commerce/lookup')]
class FlightLookupController extends AbstractController
{
    private const LIMIT = 25;

    #[Route('/airlines', name: 'flight_lookup_airlines', methods: ['GET'])]
    public function airlines(Request $request, AirlineRepository $airlineRepository): JsonResponse
    {
        return $this->json(['results' => array_map(
            static fn (Airline $airline): array => [
                'id' => $airline->getId(),
                'text' => sprintf('%s%s', $airline->getIataCode() !== null ? $airline->getIataCode() . ' - ' : '', $airline->getName()),
            ],
            $airlineRepository->search($this->query($request), self::LIMIT),
        )]);
    }

    private function query(Request $request): string
    {
        return mb_substr(trim((string) $request->query->get('q', '')), 0, 80);
    }
}
