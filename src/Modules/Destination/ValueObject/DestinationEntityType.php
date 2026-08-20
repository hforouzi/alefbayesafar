<?php

namespace App\Modules\Destination\ValueObject;

final class DestinationEntityType
{
    public const COUNTRY = 'country';
    public const STATE = 'state';
    public const CITY = 'city';
    public const DISTRICT = 'district';
    public const AIRPORT = 'airport';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [self::COUNTRY, self::STATE, self::CITY, self::DISTRICT, self::AIRPORT];
    }
}
