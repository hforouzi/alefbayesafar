<?php

namespace App\Modules\Transfer\Enum;

enum TransferAvailabilityStatus: string
{
    case AVAILABLE = 'available';
    case UNAVAILABLE = 'unavailable';
    case UNKNOWN = 'unknown';

    /**
     * @return array<string, self>
     */
    public static function choices(): array
    {
        return [
            'transfer.availability_status.available' => self::AVAILABLE,
            'transfer.availability_status.unavailable' => self::UNAVAILABLE,
            'transfer.availability_status.unknown' => self::UNKNOWN,
        ];
    }
}
