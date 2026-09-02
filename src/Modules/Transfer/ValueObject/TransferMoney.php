<?php

namespace App\Modules\Transfer\ValueObject;

final readonly class TransferMoney
{
    public static function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(self::normalizeDigits((string) $value));
        if ($value === '') {
            return null;
        }

        if (substr_count($value, ',') >= 2) {
            $value = str_replace(',', '', $value);
        }

        if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $value) !== 1) {
            return null;
        }

        if (!str_contains($value, '.')) {
            return $value . '.00';
        }

        [$major, $minor] = explode('.', $value, 2);

        return $major . '.' . str_pad($minor, 2, '0');
    }

    public static function isPositiveDecimal(?string $amount): bool
    {
        return $amount !== null && preg_match('/^[1-9]\d{0,9}(?:\.\d{2})$/', $amount) === 1;
    }

    public static function cents(string $amount): int
    {
        if (preg_match('/^\d{1,10}\.\d{2}$/', $amount) !== 1) {
            throw new \InvalidArgumentException('Amount must be normalized before converting to cents.');
        }

        [$major, $minor] = explode('.', $amount, 2);

        return ((int) $major) * 100 + (int) $minor;
    }

    public static function fromCents(int $cents): string
    {
        $major = intdiv($cents, 100);
        $minor = $cents % 100;

        return sprintf('%d.%02d', $major, $minor);
    }

    private static function normalizeDigits(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $ascii = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($arabic, $ascii, str_replace($persian, $ascii, $value));
    }
}
