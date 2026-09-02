<?php

namespace App\Modules\Transfer\Enum;

enum TransferVehicleType: string
{
    case SEDAN = 'sedan';
    case VAN = 'van';
    case MINIBUS = 'minibus';
    case BUS = 'bus';
    case OTHER = 'other';

    /**
     * @return array<string, self>
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices['transfer.vehicle_type.' . $case->value] = $case;
        }

        return $choices;
    }
}
