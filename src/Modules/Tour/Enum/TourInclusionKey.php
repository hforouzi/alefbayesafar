<?php

namespace App\Modules\Tour\Enum;

enum TourInclusionKey: string
{
    case FLIGHT = 'flight';
    case HOTEL = 'hotel';
    case BREAKFAST = 'breakfast';
    case TRANSFER = 'transfer';
    case AIRPORT_TRANSFER = 'airport_transfer';
    case ACTIVITY = 'activity';
    case CITY_TOUR = 'city_tour';
    case GUIDE = 'guide';
    case INSURANCE = 'insurance';
    case VISA = 'visa';
    case BAGGAGE = 'baggage';
    case SIM = 'sim';
    case OTHER = 'other';

    /**
     * @return array<string, string>
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices['tour.inclusion.' . $case->value] = $case->value;
        }

        return $choices;
    }

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
