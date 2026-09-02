<?php

namespace App\Modules\Tour\ValueObject;

final readonly class TourMoney
{
    public static function normalize(mixed $value): ?string
    {
        if (\is_int($value)) {
            $value = (string) $value;
        }
        if (!\is_string($value)) {
            return null;
        }

        $value = self::normalizeDigits(trim($value));
        if ($value === '') {
            return null;
        }

        if (preg_match('/^[1-9]\d{0,2}(?:,\d{3}){2,}$/', $value) === 1) {
            $value = str_replace(',', '', $value);
        }

        if (preg_match('/^[1-9]\d{0,9}(?:\.\d{1,2})?$/', $value) !== 1) {
            return null;
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

    private static function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);
    }
}
