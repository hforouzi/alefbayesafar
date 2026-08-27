<?php

namespace App\Modules\Hotel\Enum;

enum HotelBoardType: string
{
    case ROOM_ONLY = 'room_only';
    case BREAKFAST = 'breakfast';
    case HALF_BOARD = 'half_board';
    case FULL_BOARD = 'full_board';
    case ALL_INCLUSIVE = 'all_inclusive';
    case OTHER = 'other';

    public function labelKey(): string
    {
        return 'hotel.board.' . $this->value;
    }

    public static function normalize(?string $value): ?self
    {
        $value = $value !== null ? strtolower(trim($value)) : '';
        if ($value === '') {
            return null;
        }

        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);

        return match (true) {
            in_array($normalized, ['room only', 'no meal', 'no meals', 'without breakfast', 'without meal'], true) => self::ROOM_ONLY,
            str_contains($normalized, 'breakfast') => self::BREAKFAST,
            str_contains($normalized, 'half board') => self::HALF_BOARD,
            str_contains($normalized, 'full board') => self::FULL_BOARD,
            str_contains($normalized, 'all inclusive') || str_contains($normalized, 'allinclusive') => self::ALL_INCLUSIVE,
            default => self::OTHER,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function formChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->labelKey()] = $case->value;
        }

        return $choices;
    }

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
