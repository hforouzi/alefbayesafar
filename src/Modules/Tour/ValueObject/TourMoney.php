<?php

namespace App\Modules\Tour\ValueObject;

final readonly class TourMoney
{
    public static function normalize(null|string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('/^[1-9]\d{0,9}(?:\.\d{1,2})?$/', $value) !== 1) {
            return $value;
        }

        [$major, $minor] = array_pad(explode('.', $value, 2), 2, '00');

        return $major . '.' . str_pad($minor, 2, '0');
    }

    public static function isPositiveDecimal(?string $amount): bool
    {
        return $amount !== null && preg_match('/^[1-9]\d{0,9}(?:\.\d{2})$/', $amount) === 1;
    }

    public static function cents(string $amount): int
    {
        if (preg_match('/^(\d{1,10})\.(\d{2})$/', $amount, $matches) !== 1) {
            throw new \InvalidArgumentException('Money values must be normalized decimal strings.');
        }

        return ((int) $matches[1] * 100) + (int) $matches[2];
    }

    public static function fromCents(int $cents): string
    {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Money cents cannot be negative.');
        }

        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
