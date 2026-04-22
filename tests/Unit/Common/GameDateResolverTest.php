<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common;

use BeachVolleybot\Common\GameDateResolver;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GameDateResolverTest extends TestCase
{
    // --- extractRaw() ---

    public function testExtractRawReturnsNumericDate(): void
    {
        $this->assertSame('12.04', GameDateResolver::extractRaw('Beach 12.04 18:00'));
    }

    public function testExtractRawReturnsTextDate(): void
    {
        $this->assertSame('12 April', GameDateResolver::extractRaw('Beach 12 April 18:00'));
    }

    public function testExtractRawReturnsDayOfWeek(): void
    {
        $this->assertSame('Friday', GameDateResolver::extractRaw('Friday 18:00'));
    }

    public function testExtractRawDoesNotMatchTodayWord(): void
    {
        // "today" (and its localized variants) are intentionally not recognized —
        // Telegram sends UTC timestamps, which would resolve "today" to the wrong
        // local date near midnight. Users must pick a day name or explicit date.
        $this->assertNull(GameDateResolver::extractRaw('today 18:00'));
    }

    public function testExtractRawPrefersDateOverDayOfWeek(): void
    {
        $this->assertSame('12.04', GameDateResolver::extractRaw('Friday 12.04 18:00'));
    }

    public function testExtractRawReturnsNullWhenNoMatch(): void
    {
        $this->assertNull(GameDateResolver::extractRaw('Beach game 18:00'));
    }

    // --- Numeric dates with explicit year ---

    public function testResolvesNumericDateWithFullYear(): void
    {
        $now = new DateTimeImmutable('2026-03-01');

        $result = GameDateResolver::resolve('Beach 12.04.2026 18:00', $now);

        $this->assertSame('2026-04-12', $result->format('Y-m-d'));
    }

    public function testResolvesNumericDateWithShortYear(): void
    {
        $now = new DateTimeImmutable('2026-03-01');

        $result = GameDateResolver::resolve('Beach 12.04.26 18:00', $now);

        $this->assertSame('2026-04-12', $result->format('Y-m-d'));
    }

    // --- Numeric dates without year (year resolution) ---

    public function testResolvesNumericDateInFuture(): void
    {
        $now = new DateTimeImmutable('2026-03-01');

        $result = GameDateResolver::resolve('Beach 12.04 18:00', $now);

        $this->assertSame('2026-04-12', $result->format('Y-m-d'));
    }

    public function testResolvesNumericDateInRecentPast(): void
    {
        $now = new DateTimeImmutable('2026-04-15');

        $result = GameDateResolver::resolve('Beach 10.03 18:00', $now);

        $this->assertSame('2026-03-10', $result->format('Y-m-d'));
    }

    public function testNewYearProblemDecemberToJanuary(): void
    {
        $now = new DateTimeImmutable('2025-12-01');

        $result = GameDateResolver::resolve('Beach 5.01 18:00', $now);

        $this->assertSame('2026-01-05', $result->format('Y-m-d'));
    }

    public function testNewYearProblemJanuaryToDecember(): void
    {
        $now = new DateTimeImmutable('2026-01-03');

        $result = GameDateResolver::resolve('Beach 28.12 18:00', $now);

        $this->assertSame('2025-12-28', $result->format('Y-m-d'));
    }

    public function testResolvesNumericDateSingleDigitDay(): void
    {
        $now = new DateTimeImmutable('2026-04-01');

        $result = GameDateResolver::resolve('Beach 5.04 18:00', $now);

        $this->assertSame('2026-04-05', $result->format('Y-m-d'));
    }

    // --- Text dates (English) ---

    public function testResolvesEnglishDayMonth(): void
    {
        $now = new DateTimeImmutable('2026-03-01');

        $result = GameDateResolver::resolve('Beach 12 April 18:00', $now);

        $this->assertSame('2026-04-12', $result->format('Y-m-d'));
    }

    public function testResolvesEnglishMonthDay(): void
    {
        $now = new DateTimeImmutable('2026-03-01');

        $result = GameDateResolver::resolve('Beach April 12 18:00', $now);

        $this->assertSame('2026-04-12', $result->format('Y-m-d'));
    }

    public function testResolvesEnglishShortMonth(): void
    {
        $now = new DateTimeImmutable('2026-03-01');

        $result = GameDateResolver::resolve('Beach 12 Apr 18:00', $now);

        $this->assertSame('2026-04-12', $result->format('Y-m-d'));
    }

    public function testResolvesEnglishOrdinalDay(): void
    {
        $now = new DateTimeImmutable('2026-03-01');

        $result = GameDateResolver::resolve('Beach April 5th 18:00', $now);

        $this->assertSame('2026-04-05', $result->format('Y-m-d'));
    }

    public function testResolvesEnglishOrdinalDayOfMonth(): void
    {
        $now = new DateTimeImmutable('2026-02-01');

        $result = GameDateResolver::resolve('Beach 1st of March 18:00', $now);

        $this->assertSame('2026-03-01', $result->format('Y-m-d'));
    }

    // --- Text dates (Russian) ---

    public function testResolvesRussianGenitiveMonth(): void
    {
        $now = new DateTimeImmutable('2026-03-01');

        $result = GameDateResolver::resolve('Игра 12 апреля 18:00', $now);

        $this->assertSame('2026-04-12', $result->format('Y-m-d'));
    }

    public function testResolvesRussianNominativeMonth(): void
    {
        $now = new DateTimeImmutable('2026-03-01');

        $result = GameDateResolver::resolve('Игра 12 апрель 18:00', $now);

        $this->assertSame('2026-04-12', $result->format('Y-m-d'));
    }

    public function testResolvesRussianShortMonth(): void
    {
        $now = new DateTimeImmutable('2026-03-01');

        $result = GameDateResolver::resolve('Игра 12 апр 18:00', $now);

        $this->assertSame('2026-04-12', $result->format('Y-m-d'));
    }

    // --- Text dates (Spanish) ---

    public function testResolvesSpanishWithDe(): void
    {
        $now = new DateTimeImmutable('2026-03-01');

        $result = GameDateResolver::resolve('Juego 12 de abril 18:00', $now);

        $this->assertSame('2026-04-12', $result->format('Y-m-d'));
    }

    public function testResolvesSpanishWithoutDe(): void
    {
        $now = new DateTimeImmutable('2026-03-01');

        $result = GameDateResolver::resolve('Juego 12 abril 18:00', $now);

        $this->assertSame('2026-04-12', $result->format('Y-m-d'));
    }

    // --- Day-of-week resolution ---

    public function testResolvesFridayFromWednesday(): void
    {
        $creationDate = new DateTimeImmutable('2026-04-15'); // Wednesday

        $result = GameDateResolver::resolve('Friday 18:00', $creationDate);

        $this->assertSame('2026-04-17', $result->format('Y-m-d'));
    }

    public function testResolvesFridayFromFriday(): void
    {
        $creationDate = new DateTimeImmutable('2026-04-17'); // Friday

        $result = GameDateResolver::resolve('Friday 18:00', $creationDate);

        $this->assertSame('2026-04-17', $result->format('Y-m-d'));
    }

    public function testResolvesFridayFromSaturday(): void
    {
        $creationDate = new DateTimeImmutable('2026-04-18'); // Saturday

        $result = GameDateResolver::resolve('Friday 18:00', $creationDate);

        $this->assertSame('2026-04-24', $result->format('Y-m-d'));
    }

    public function testResolvesRussianDayOfWeek(): void
    {
        $creationDate = new DateTimeImmutable('2026-04-15'); // Wednesday

        $result = GameDateResolver::resolve('Пятница 18:00', $creationDate);

        $this->assertSame('2026-04-17', $result->format('Y-m-d'));
    }

    public function testResolvesSpanishDayOfWeek(): void
    {
        $creationDate = new DateTimeImmutable('2026-04-15'); // Wednesday

        $result = GameDateResolver::resolve('Viernes 18:00', $creationDate);

        $this->assertSame('2026-04-17', $result->format('Y-m-d'));
    }

    // --- "Today" variants are intentionally rejected ---
    // Telegram updates arrive in UTC, so "today" would resolve to the wrong
    // local date near midnight. Users must pick a day name or explicit date.

    public function testDoesNotResolveEnglishToday(): void
    {
        $creationDate = new DateTimeImmutable('2026-04-16');

        $this->assertNull(GameDateResolver::resolve('Today 18:00', $creationDate));
    }

    public function testDoesNotResolveRussianToday(): void
    {
        $creationDate = new DateTimeImmutable('2026-04-16');

        $this->assertNull(GameDateResolver::resolve('Сегодня 18:00', $creationDate));
    }

    public function testDoesNotResolveSpanishToday(): void
    {
        $creationDate = new DateTimeImmutable('2026-04-16');

        $this->assertNull(GameDateResolver::resolve('Hoy 18:00', $creationDate));
    }

    // --- No date / ambiguous ---

    public function testReturnsNullForTimeOnly(): void
    {
        $creationDate = new DateTimeImmutable('2026-04-16');

        $this->assertNull(GameDateResolver::resolve('Beach game 18:00', $creationDate));
    }

    public function testReturnsNullForEmptyTitle(): void
    {
        $creationDate = new DateTimeImmutable('2026-04-16');

        $this->assertNull(GameDateResolver::resolve('', $creationDate));
    }

    // --- Date takes priority over day-of-week ---

    public function testDatePatternTakesPriorityOverDayOfWeek(): void
    {
        $now = new DateTimeImmutable('2026-03-01');

        $result = GameDateResolver::resolve('Friday 12.04 18:00', $now);

        $this->assertSame('2026-04-12', $result->format('Y-m-d'));
    }

    // --- Year resolution uses creation date, not current date ---

    public function testResolvesYearRelativeToCreationDate(): void
    {
        $creationDate = new DateTimeImmutable('2024-03-01');

        $result = GameDateResolver::resolve('Beach 12.04 18:00', $creationDate);

        $this->assertSame('2024-04-12', $result->format('Y-m-d'));
    }

    // --- Invalid date ---

    public function testReturnsNullForInvalidDate(): void
    {
        $now = new DateTimeImmutable('2026-03-01');

        $this->assertNull(GameDateResolver::resolve('Beach 30.02 18:00', $now));
    }
}
