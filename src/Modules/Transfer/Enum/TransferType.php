<?php

namespace App\Modules\Transfer\Enum;

enum TransferType: string
{
    case PRIVATE = 'private';
    case SHARED = 'shared';
    case SHUTTLE = 'shuttle';
    case OTHER = 'other';

    /**
     * @return array<string, self>
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices['transfer.type.' . $case->value] = $case;
        }

        return $choices;
    }
}
