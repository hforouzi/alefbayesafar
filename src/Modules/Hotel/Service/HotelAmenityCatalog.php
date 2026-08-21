<?php

namespace App\Modules\Hotel\Service;

use App\Modules\Hotel\Entity\HotelAmenity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;

final readonly class HotelAmenityCatalog
{
    /**
     * @return array<string, array{name: string, nameFa: string, patterns: string[]}>
     */
    public function definitions(): array
    {
        return [
            'wifi' => ['name' => 'WiFi', 'nameFa' => 'وای‌فای', 'patterns' => ['wifi', 'wi-fi', 'wireless internet', 'free internet', 'wlan']],
            'pool' => ['name' => 'Swimming pool', 'nameFa' => 'استخر', 'patterns' => ['indoor pool', 'outdoor pool', 'swimming pool', 'pool']],
            'spa' => ['name' => 'Spa', 'nameFa' => 'اسپا', 'patterns' => ['spa and wellness', 'spa', 'wellness']],
            'parking' => ['name' => 'Parking', 'nameFa' => 'پارکینگ', 'patterns' => ['private parking', 'parking', 'car park']],
            'airport_shuttle' => ['name' => 'Airport shuttle', 'nameFa' => 'ترانسفر فرودگاه', 'patterns' => ['airport shuttle', 'airport transfer']],
            'fitness_center' => ['name' => 'Fitness center', 'nameFa' => 'باشگاه ورزشی', 'patterns' => ['fitness center', 'fitness centre', 'fitness', 'gym']],
            'family_rooms' => ['name' => 'Family rooms', 'nameFa' => 'اتاق خانوادگی', 'patterns' => ['family room', 'family rooms']],
            'non_smoking_rooms' => ['name' => 'Non-smoking rooms', 'nameFa' => 'اتاق غیرسیگاری', 'patterns' => ['non-smoking room', 'non-smoking rooms', 'non smoking room', 'non smoking rooms']],
            'breakfast' => ['name' => 'Breakfast', 'nameFa' => 'صبحانه', 'patterns' => ['breakfast available', 'breakfast']],
            'restaurant' => ['name' => 'Restaurant', 'nameFa' => 'رستوران', 'patterns' => ['restaurant']],
            'bar' => ['name' => 'Bar', 'nameFa' => 'بار', 'patterns' => ['bar']],
        ];
    }

    /**
     * @return string[]
     */
    public function codesFromText(string $text): array
    {
        $text = mb_strtolower($text);
        $codes = [];
        foreach ($this->definitions() as $code => $definition) {
            foreach ($definition['patterns'] as $pattern) {
                if (str_contains($text, $pattern)) {
                    $codes[$code] = $code;
                    break;
                }
            }
        }

        return array_values($codes);
    }

    /**
     * @param ObjectRepository<HotelAmenity> $repository
     */
    public function seed(ObjectRepository $repository, EntityManagerInterface $entityManager): int
    {
        $created = 0;
        foreach ($this->definitions() as $code => $definition) {
            $amenity = $repository->findOneBy(['code' => HotelAmenity::normalizeCode($code)]);
            if (!$amenity instanceof HotelAmenity) {
                $amenity = new HotelAmenity();
                $entityManager->persist($amenity);
                ++$created;
            }

            $amenity
                ->setCode($code)
                ->setName($definition['name'])
                ->setNameFa($definition['nameFa'])
                ->setActive(true);
        }

        return $created;
    }
}
