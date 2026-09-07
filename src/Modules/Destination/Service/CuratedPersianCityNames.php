<?php

namespace App\Modules\Destination\Service;

/**
 * Small, deterministic Persian-name fallback for well-known cities.
 *
 * Used only when the primary source (GeoNames alternate names, see
 * PersianDestinationNameEnrichmentService) does not already provide a
 * Persian name for a city. Never overwrites an existing name_fa. This is
 * intentionally a short, curated list of major/travel-relevant cities —
 * not a general translation mechanism — so any city not listed here is
 * reported as still missing rather than guessed.
 *
 * Keyed by country ISO2 => English city name (matched case-insensitively,
 * trimmed) => Persian name.
 */
final class CuratedPersianCityNames
{
    /**
     * @var array<string, array<string, string>>
     */
    public const NAMES = [
        'IR' => [
            'Tehran' => 'تهران',
            'Rasht' => 'رشت',
            'Mashhad' => 'مشهد',
            'Isfahan' => 'اصفهان',
            'Esfahan' => 'اصفهان',
            'Shiraz' => 'شیراز',
            'Tabriz' => 'تبریز',
            'Kish' => 'کیش',
            'Qeshm' => 'قشم',
            'Ahvaz' => 'اهواز',
            'Kermanshah' => 'کرمانشاه',
            'Yazd' => 'یزد',
            'Karaj' => 'کرج',
            'Qom' => 'قم',
            'Bandar Abbas' => 'بندرعباس',
            'Sari' => 'ساری',
            'Urmia' => 'ارومیه',
        ],
        'TR' => [
            'Istanbul' => 'استانبول',
            'Ankara' => 'آنکارا',
            'Antalya' => 'آنتالیا',
            'Izmir' => 'ازمیر',
            'İzmir' => 'ازمیر',
            'Bodrum' => 'بدروم',
            'Bursa' => 'بورسا',
            'Trabzon' => 'ترابزون',
            'Alanya' => 'آلانیا',
            'Marmaris' => 'مارماریس',
            'Konya' => 'قونیه',
            'Adana' => 'آدانا',
        ],
        'AE' => [
            'Dubai' => 'دبی',
            'Abu Dhabi' => 'ابوظبی',
            'Sharjah' => 'شارجه',
        ],
        'AL' => [
            'Tirana' => 'تیرانا',
        ],
        'GE' => [
            'Tbilisi' => 'تفلیس',
            'Batumi' => 'باتومی',
        ],
        'AM' => [
            'Yerevan' => 'ایروان',
        ],
        'AZ' => [
            'Baku' => 'باکو',
        ],
        'RU' => [
            'Moscow' => 'مسکو',
            'Saint Petersburg' => 'سن پترزبورگ',
        ],
        'TH' => [
            'Bangkok' => 'بانکوک',
            'Phuket' => 'پوکت',
        ],
        'MY' => [
            'Kuala Lumpur' => 'کوالالامپور',
        ],
        'GB' => [
            'London' => 'لندن',
        ],
        'FR' => [
            'Paris' => 'پاریس',
        ],
        'DE' => [
            'Berlin' => 'برلین',
        ],
        'IT' => [
            'Rome' => 'رم',
        ],
        'ES' => [
            'Madrid' => 'مادرید',
            'Barcelona' => 'بارسلونا',
        ],
        'US' => [
            'New York' => 'نیویورک',
        ],
        'CN' => [
            'Beijing' => 'پکن',
            'Shanghai' => 'شانگهای',
        ],
        'JP' => [
            'Tokyo' => 'توکیو',
        ],
        'QA' => [
            'Doha' => 'دوحه',
        ],
        'SA' => [
            'Riyadh' => 'ریاض',
            'Jeddah' => 'جده',
            'Mecca' => 'مکه',
            'Medina' => 'مدینه',
        ],
        'KW' => [
            'Kuwait City' => 'کویت',
        ],
        'OM' => [
            'Muscat' => 'مسقط',
        ],
        'IN' => [
            'Delhi' => 'دهلی',
            'New Delhi' => 'دهلی نو',
            'Mumbai' => 'بمبئی',
        ],
    ];

    public static function find(?string $countryIso2, string $cityName): ?string
    {
        if ($countryIso2 === null) {
            return null;
        }

        $countryEntries = self::NAMES[strtoupper($countryIso2)] ?? null;
        if ($countryEntries === null) {
            return null;
        }

        $normalizedTarget = self::normalize($cityName);
        foreach ($countryEntries as $name => $persianName) {
            if (self::normalize($name) === $normalizedTarget) {
                return $persianName;
            }
        }

        return null;
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
