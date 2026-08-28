<?php

namespace App\Modules\Flight\ValueObject;

final readonly class FlightMoney
{
    public static function normalize(mixed $value): ?string
    {
        if (\is_int($value)) {
            return $value > 0 ? sprintf('%d.00', $value) : null;
        }

        $value = \is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/\s+/u', '', $value) ?? $value;
        if (preg_match('/^[1-9]\d{0,9}(?:\.\d{1,2})?$/', $value) !== 1) {
            return null;
        }

        [$major, $minor] = array_pad(explode('.', $value, 2), 2, '00');

        return $major . '.' . str_pad($minor, 2, '0');
    }

    public static function cents(string $amount): int
    {
        if (preg_match('/^(\d{1,10})(?:\.(\d{2}))$/', $amount, $matches) !== 1) {
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

    public static function multiply(string $amount, int $multiplier): string
    {
        if ($multiplier < 0) {
            throw new \InvalidArgumentException('Money multiplier cannot be negative.');
        }

        return self::fromCents(self::cents($amount) * $multiplier);
    }
}
