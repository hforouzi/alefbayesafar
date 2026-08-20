<?php

namespace App\Shared\Date;

final readonly class LocaleDateTimeFormatter
{
    private const PERSIAN_DIGITS = [
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
    ];

    /** @var int[] */
    private const GREGORIAN_DAYS = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

    /** @var int[] */
    private const JALALI_DAYS = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

    public function __construct(private string $timezone = '')
    {
    }

    public function formatDate(?\DateTimeInterface $date, string $locale): string
    {
        if (!$date instanceof \DateTimeInterface) {
            return '';
        }

        $date = $this->inTimezone($date);

        if ($locale === 'fa') {
            [$year, $month, $day] = self::gregorianToJalali((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));

            return sprintf('%04d/%02d/%02d', $year, $month, $day);
        }

        return $date->format('Y-m-d');
    }

    public function formatDateTime(?\DateTimeInterface $date, string $locale): string
    {
        if (!$date instanceof \DateTimeInterface) {
            return '';
        }

        $date = $this->inTimezone($date);

        return $this->formatDate($date, $locale) . ' ' . $date->format('H:i');
    }

    public function parseDate(?string $value, string $locale): ?\DateTimeImmutable
    {
        $value = trim(self::normalizeDigits((string) $value));
        if ($value === '') {
            return null;
        }

        if ($locale === 'fa') {
            if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $value, $matches) !== 1) {
                return null;
            }

            $jalali = [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
            if (!$this->isValidJalaliDate($jalali[0], $jalali[1], $jalali[2])) {
                return null;
            }

            [$year, $month, $day] = self::jalaliToGregorian($jalali[0], $jalali[1], $jalali[2]);

            return new \DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day), $this->timezone());
        }

        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $value, $matches) !== 1) {
            return null;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day), $this->timezone());
    }

    public function parseToCanonicalDate(?string $value, string $locale): string
    {
        return $this->parseDate($value, $locale)?->format('Y-m-d') ?? '';
    }

    public function startOfDay(string $canonicalDate): ?\DateTimeImmutable
    {
        return $this->dateBoundary($canonicalDate, '00:00:00');
    }

    public function endOfDay(string $canonicalDate): ?\DateTimeImmutable
    {
        return $this->dateBoundary($canonicalDate, '23:59:59');
    }

    public function isValidRange(string $from, string $to): bool
    {
        if ($from === '' || $to === '') {
            return true;
        }

        return $from <= $to;
    }

    public static function normalizeDigits(string $value): string
    {
        return strtr($value, self::PERSIAN_DIGITS);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        $gy -= 1600;
        --$gm;
        --$gd;

        $dayNo = 365 * $gy + intdiv($gy + 3, 4) - intdiv($gy + 99, 100) + intdiv($gy + 399, 400);
        for ($i = 0; $i < $gm; ++$i) {
            $dayNo += self::GREGORIAN_DAYS[$i];
        }
        if ($gm > 1 && (($gy + 1600) % 4 === 0 && (($gy + 1600) % 100 !== 0 || ($gy + 1600) % 400 === 0))) {
            ++$dayNo;
        }
        $dayNo += $gd;

        $jalaliDayNo = $dayNo - 79;
        $cycle = intdiv($jalaliDayNo, 12053);
        $jalaliDayNo %= 12053;

        $jy = 979 + 33 * $cycle + 4 * intdiv($jalaliDayNo, 1461);
        $jalaliDayNo %= 1461;

        if ($jalaliDayNo >= 366) {
            $jy += intdiv($jalaliDayNo - 1, 365);
            $jalaliDayNo = ($jalaliDayNo - 1) % 365;
        }

        for ($jm = 0; $jm < 11 && $jalaliDayNo >= self::JALALI_DAYS[$jm]; ++$jm) {
            $jalaliDayNo -= self::JALALI_DAYS[$jm];
        }

        return [$jy, $jm + 1, $jalaliDayNo + 1];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function jalaliToGregorian(int $jy, int $jm, int $jd): array
    {
        $jy -= 979;
        --$jm;
        --$jd;

        $dayNo = 365 * $jy + intdiv($jy, 33) * 8 + intdiv(($jy % 33) + 3, 4);
        for ($i = 0; $i < $jm; ++$i) {
            $dayNo += self::JALALI_DAYS[$i];
        }
        $dayNo += $jd;

        $gregorianDayNo = $dayNo + 79;
        $gy = 1600 + 400 * intdiv($gregorianDayNo, 146097);
        $gregorianDayNo %= 146097;

        $leap = true;
        if ($gregorianDayNo >= 36525) {
            --$gregorianDayNo;
            $gy += 100 * intdiv($gregorianDayNo, 36524);
            $gregorianDayNo %= 36524;

            if ($gregorianDayNo >= 365) {
                ++$gregorianDayNo;
            } else {
                $leap = false;
            }
        }

        $gy += 4 * intdiv($gregorianDayNo, 1461);
        $gregorianDayNo %= 1461;

        if ($gregorianDayNo >= 366) {
            $leap = false;
            --$gregorianDayNo;
            $gy += intdiv($gregorianDayNo, 365);
            $gregorianDayNo %= 365;
        }

        for ($gm = 0; $gm < 11; ++$gm) {
            $monthDays = self::GREGORIAN_DAYS[$gm] + ($gm === 1 && $leap ? 1 : 0);
            if ($gregorianDayNo < $monthDays) {
                break;
            }
            $gregorianDayNo -= $monthDays;
        }

        return [$gy, $gm + 1, $gregorianDayNo + 1];
    }

    private function dateBoundary(string $canonicalDate, string $time): ?\DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $canonicalDate) !== 1) {
            return null;
        }

        return new \DateTimeImmutable($canonicalDate . ' ' . $time, $this->timezone());
    }

    private function isValidJalaliDate(int $year, int $month, int $day): bool
    {
        if ($year < 1 || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return false;
        }

        [$gy, $gm, $gd] = self::jalaliToGregorian($year, $month, $day);
        [$jy, $jm, $jd] = self::gregorianToJalali($gy, $gm, $gd);

        return [$year, $month, $day] === [$jy, $jm, $jd];
    }

    private function inTimezone(\DateTimeInterface $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($date)->setTimezone($this->timezone());
    }

    private function timezone(): \DateTimeZone
    {
        return new \DateTimeZone($this->timezone !== '' ? $this->timezone : date_default_timezone_get());
    }
}
