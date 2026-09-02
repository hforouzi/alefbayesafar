<?php

namespace App\Modules\Activity\Enum;

enum ActivityPricingMode: string
{
    case PER_PERSON_TYPE = 'per_person_type';
    case TOTAL_PARTY = 'total_party';

    /**
     * @return array<string, self>
     */
    public static function choices(): array
    {
        return [
            'activity.pricing_mode.per_person_type' => self::PER_PERSON_TYPE,
            'activity.pricing_mode.total_party' => self::TOTAL_PARTY,
        ];
    }
}
