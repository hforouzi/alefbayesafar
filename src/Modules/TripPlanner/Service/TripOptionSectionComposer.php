<?php

namespace App\Modules\TripPlanner\Service;

use App\Modules\TripPlanner\ValueObject\TripComponentSummary;
use App\Modules\TripPlanner\ValueObject\TripOption;

/**
 * Groups the flight/hotel/activity/transfer components already attached to
 * the currently shown TripOptions into Lovable-style page sections
 * (Flight / Hotel / Activities / Services), deduplicated across options.
 *
 * Purely presentational: it never searches anything itself, it only
 * regroups facts TripPlanner already computed on TripOption::$components.
 */
final class TripOptionSectionComposer
{
    private const MAX_PER_SECTION = 6;

    /**
     * @param TripOption[] $options
     *
     * @return array{flights: TripComponentSummary[], hotels: TripComponentSummary[], activities: TripComponentSummary[], services: TripComponentSummary[]}
     */
    public function compose(array $options): array
    {
        $byType = ['flight' => [], 'hotel' => [], 'activity' => [], 'transfer' => []];
        foreach ($options as $option) {
            foreach ($option->components as $component) {
                if (!isset($byType[$component->type])) {
                    continue;
                }
                $key = $component->title . '|' . $component->price . '|' . $component->currency;
                $byType[$component->type][$key] ??= $component;
            }
        }

        return [
            'flights' => \array_slice(array_values($byType['flight']), 0, self::MAX_PER_SECTION),
            'hotels' => \array_slice(array_values($byType['hotel']), 0, self::MAX_PER_SECTION),
            'activities' => \array_slice(array_values($byType['activity']), 0, self::MAX_PER_SECTION),
            'services' => \array_slice(array_values($byType['transfer']), 0, self::MAX_PER_SECTION),
        ];
    }
}
