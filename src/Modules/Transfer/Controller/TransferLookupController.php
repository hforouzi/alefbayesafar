<?php

namespace App\Modules\Transfer\Controller;

use App\Modules\Hotel\Entity\Hotel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/transfer-commerce/lookup')]
class TransferLookupController extends AbstractController
{
    private const LIMIT = 25;

    #[Route('/hotels', name: 'transfer_lookup_hotels', methods: ['GET'])]
    public function hotels(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $query = mb_substr(trim((string) $request->query->get('q', '')), 0, 80);
        $builder = $entityManager->getRepository(Hotel::class)->createQueryBuilder('hotel')
            ->leftJoin('hotel.city', 'city')
            ->addSelect('city')
            ->andWhere('hotel.active = true')
            ->orderBy('hotel.name', 'ASC')
            ->setMaxResults(self::LIMIT);

        if ($query !== '') {
            $builder
                ->andWhere('hotel.name LIKE :query OR hotel.nameFa LIKE :query OR city.name LIKE :query OR city.nameFa LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        return $this->json(['results' => array_map(
            static fn (Hotel $hotel): array => [
                'id' => $hotel->getId(),
                'text' => sprintf('%s - %s', (string) $hotel, $hotel->getCity()?->getName() ?? ''),
            ],
            $builder->getQuery()->getResult(),
        )]);
    }
}
