<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common;

use BeachVolleybot\Common\GameDateTimeResolver;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GameDateTimeResolverTest extends TestCase
{
    // --- date in title + time ---

    public function testCombinesNumericDateWithTime(): void
    {
        $result = GameDateTimeResolver::resolve('Beach 12.04.2026 18:00', new DateTimeImmutable('2026-03-01'));

        $this->assertNotNull($result);
        $this->assertSame('2026-04-12 18:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testCombinesTextualDateWithTime(): void
    {
        $result = GameDateTimeResolver::resolve('Beach 12 April 18:30', new DateTimeImmutable('2026-03-01'));

        $this->assertNotNull($result);
        $this->assertSame('2026-04-12 18:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function testPreservesMinutes(): void
    {
        $result = GameDateTimeResolver::resolve('Bogatell 12.04 18:45', new DateTimeImmutable('2026-03-01'));

        $this->assertNotNull($result);
        $this->assertSame(45, (int) $result->format('i'));
    }

    // --- day-of-week resolution ---

    public function testResolvesDayOfWeekRelativeToCreationDate(): void
    {
        // 2026-04-10 is a Friday; "Saturday" resolves to 2026-04-11.
        $result = GameDateTimeResolver::resolve('Bogatell Saturday 18:30', new DateTimeImmutable('2026-04-10'));

        $this->assertNotNull($result);
        $this->assertSame('2026-04-11 18:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function testTodayKeywordFallsBackToCreationDateBecauseItIsNotExtractable(): void
    {
        // "today" is intentionally not recognized (Telegram updates arrive in UTC,
        // which would resolve "today" to the wrong local date near midnight).
        // With no extractable date, resolution falls back to the creation date.
        $result = GameDateTimeResolver::resolve('today 18:30', new DateTimeImmutable('2026-04-10'));

        $this->assertNotNull($result);
        $this->assertSame('2026-04-10 18:30:00', $result->format('Y-m-d H:i:s'));
    }

    // --- fallback: no date in title → uses creation date ---

    public function testFallsBackToCreationDateWhenNoDateInTitle(): void
    {
        $result = GameDateTimeResolver::resolve('Bogatell 18:30', new DateTimeImmutable('2026-04-10'));

        $this->assertNotNull($result);
        $this->assertSame('2026-04-10 18:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function testFallbackPreservesExplicitMinutes(): void
    {
        $result = GameDateTimeResolver::resolve('Bogatell 8:05', new DateTimeImmutable('2026-04-10'));

        $this->assertNotNull($result);
        $this->assertSame('2026-04-10 08:05:00', $result->format('Y-m-d H:i:s'));
    }

    // --- null paths ---

    public function testReturnsNullWhenNoTimeInTitle(): void
    {
        $this->assertNull(
            GameDateTimeResolver::resolve('Bogatell Saturday', new DateTimeImmutable('2026-04-10')),
        );
    }

    public function testReturnsNullForEmptyTitle(): void
    {
        $this->assertNull(GameDateTimeResolver::resolve('', new DateTimeImmutable('2026-04-10')));
    }
}