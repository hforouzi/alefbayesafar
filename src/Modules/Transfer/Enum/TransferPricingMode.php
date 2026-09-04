<?php

namespace App\Modules\Transfer\Enum;

enum TransferPricingMode: string
{
    case TOTAL_SERVICE = 'total_service';
    case TOTAL_PARTY = 'total_party';

    /**
     * @return array<string, self>
     */
    public static function choices(): array
    {
        return [
            'transfer.pricing_mode.total_service' => self::TOTAL_SERVICE,
            'transfer.pricing_mode.total_party' => self::TOTAL_PARTY,
        ];
    }
}
