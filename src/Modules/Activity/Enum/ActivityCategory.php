<?php

namespace App\Modules\Activity\Enum;

enum ActivityCategory: string
{
    case SIGHTSEEING = 'sightseeing';
    case ATTRACTION = 'attraction';
    case MUSEUM = 'museum';
    case CRUISE = 'cruise';
    case SHOW = 'show';
    case FOOD = 'food';
    case ADVENTURE = 'adventure';
    case DAY_TRIP = 'day_trip';
    case OTHER = 'other';

    public function labelKey(): string
    {
        return 'activity.category.' . $this->value;
    }

    /**
     * @return array<string, self>
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->labelKey()] = $case;
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
