<?php

namespace App\Modules\TripPlanner\ValueObject;

final readonly class TripMoney
{
    public static function normalize(?string $amount): ?string
    {
        if ($amount === null) {
            return null;
        }

        $amount = trim($amount);
        if ($amount === '') {
            return null;
        }

        if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $amount) !== 1) {
            throw new \InvalidArgumentException('Money values must be decimal strings.');
        }

        [$major, $minor] = array_pad(explode('.', $amount, 2), 2, '00');

        return $major . '.' . str_pad($minor, 2, '0');
    }

    public static function cents(string $amount): int
    {
        $normalized = self::normalize($amount);
        if ($normalized === null) {
            throw new \InvalidArgumentException('Money value is required.');
        }

        [$major, $minor] = explode('.', $normalized, 2);

        return ((int) $major * 100) + (int) $minor;
    }

    /**
     * @param string[] $amounts
     */
    public static function sum(array $amounts): string
    {
        $total = 0;
        foreach ($amounts as $amount) {
            $total += self::cents($amount);
        }

        return self::fromCents($total);
    }

    public static function fromCents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
