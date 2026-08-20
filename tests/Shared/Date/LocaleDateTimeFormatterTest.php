<?php

namespace App\Tests\Shared\Date;

use App\Shared\Date\LocaleDateTimeFormatter;
use PHPUnit\Framework\TestCase;

final class LocaleDateTimeFormatterTest extends TestCase
{
    private LocaleDateTimeFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new LocaleDateTimeFormatter('Europe/Berlin');
    }

    public function testGregorianDateDisplaysAsJalaliForPersianLocale(): void
    {
        $date = new \DateTimeImmutable('2026-08-20 13:21:00', new \DateTimeZone('Europe/Berlin'));

        self::assertSame('1405/05/29', $this->formatter->formatDate($date, 'fa'));
        self::assertSame('1405/05/29 13:21', $this->formatter->formatDateTime($date, 'fa'));
    }

    public function testGregorianDateDisplaysAsGregorianForEnglishLocale(): void
    {
        $date = new \DateTimeImmutable('2026-08-20 13:21:00', new \DateTimeZone('Europe/Berlin'));

        self::assertSame('2026-08-20', $this->formatter->formatDate($date, 'en'));
        self::assertSame('2026-08-20 13:21', $this->formatter->formatDateTime($date, 'en'));
    }

    public function testJalaliInputParsesToGregorianDate(): void
    {
        self::assertSame('2026-08-20', $this->formatter->parseToCanonicalDate('1405/05/29', 'fa'));
    }

    public function testPersianDigitsAreNormalizedDuringParsing(): void
    {
        self::assertSame('2026-08-20', $this->formatter->parseToCanonicalDate('۱۴۰۵/۰۵/۲۹', 'fa'));
    }

    public function testInvalidJalaliDateIsRejected(): void
    {
        self::assertSame('', $this->formatter->parseToCanonicalDate('1405/13/01', 'fa'));
        self::assertSame('', $this->formatter->parseToCanonicalDate('1405/02/32', 'fa'));
    }

    public function testGregorianLeapBoundaryConvertsCorrectly(): void
    {
        self::assertSame('1403/01/01', $this->formatter->formatDate(new \DateTimeImmutable('2024-03-20'), 'fa'));
        self::assertSame('2024-03-20', $this->formatter->parseToCanonicalDate('1403/01/01', 'fa'));
    }

    public function testDateRangeBoundariesUseApplicationTimezone(): void
    {
        self::assertSame('2026-08-20 00:00:00 Europe/Berlin', $this->formatter->startOfDay('2026-08-20')?->format('Y-m-d H:i:s e'));
        self::assertSame('2026-08-20 23:59:59 Europe/Berlin', $this->formatter->endOfDay('2026-08-20')?->format('Y-m-d H:i:s e'));
    }

    public function testFromAfterToIsInvalid(): void
    {
        self::assertFalse($this->formatter->isValidRange('2026-08-21', '2026-08-20'));
        self::assertTrue($this->formatter->isValidRange('2026-08-20', '2026-08-21'));
    }

    public function testEmptyDateFallback(): void
    {
        self::assertSame('', $this->formatter->formatDate(null, 'fa'));
        self::assertNull($this->formatter->parseDate('', 'fa'));
    }
}
