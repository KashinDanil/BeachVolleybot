<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common\Extractors;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use PHPUnit\Framework\TestCase;

final class TimeExtractorTest extends TestCase
{
    // --- extract (normalized) ---

    public function testExtractsTimeWithColon(): void
    {
        $this->assertSame('18:00', TimeExtractor::extract('Beach Game 18:00'));
    }

    public function testNormalizesLeadingZero(): void
    {
        $this->assertSame('09:30', TimeExtractor::extract('Game 9:30'));
    }

    public function testExtractsFirstTimeWhenMultiple(): void
    {
        $this->assertSame('10:00', TimeExtractor::extract('Game 10:00 or 11:00'));
    }

    public function testReturnsNullWhenNoTime(): void
    {
        $this->assertNull(TimeExtractor::extract('Beach Game'));
    }

    public function testReturnsNullForEmptyString(): void
    {
        $this->assertNull(TimeExtractor::extract(''));
    }

    public function testReturnsNullForPartialTime(): void
    {
        $this->assertNull(TimeExtractor::extract('Game 18'));
    }

    public function testExtractsTimeAtStartOfString(): void
    {
        $this->assertSame('18:00', TimeExtractor::extract('18:00 Beach Game'));
    }

    public function testExtractsTimeAtEndOfString(): void
    {
        $this->assertSame('18:00', TimeExtractor::extract('Beach Game 18:00'));
    }

    // --- extractRaw (original format) ---

    public function testExtractsRawTimePreservingOriginalFormat(): void
    {
        $this->assertSame('9:30', TimeExtractor::extractRaw('Game 9:30'));
    }

    public function testExtractsRawTimeWithLeadingZero(): void
    {
        $this->assertSame('09:30', TimeExtractor::extractRaw('Game 09:30'));
    }

    public function testExtractsRawFirstTimeWhenMultiple(): void
    {
        $this->assertSame('10:00', TimeExtractor::extractRaw('Game 10:00 or 11:00'));
    }

    public function testExtractRawReturnsNullWhenNoTime(): void
    {
        $this->assertNull(TimeExtractor::extractRaw('Beach Game'));
    }

    // --- normalize ---

    public function testNormalizeShortTimeFormat(): void
    {
        $this->assertSame('Beach 08:00', TimeExtractor::normalize('Beach 8:00'));
    }

    public function testNormalizeKeepsFullTimeFormat(): void
    {
        $this->assertSame('Beach 18:00', TimeExtractor::normalize('Beach 18:00'));
    }

    public function testNormalizeReturnsTextWithoutTime(): void
    {
        $this->assertSame('No time here', TimeExtractor::normalize('No time here'));
    }

    // --- isTimeOnly ---

    public function testIsTimeOnlyReturnsTrueForBareTime(): void
    {
        $this->assertTrue(TimeExtractor::isTimeOnly('15:30'));
    }

    public function testIsTimeOnlyReturnsTrueForSingleDigitHour(): void
    {
        $this->assertTrue(TimeExtractor::isTimeOnly('9:00'));
    }

    public function testIsTimeOnlyReturnsTrueForTimeWithSurroundingWhitespace(): void
    {
        $this->assertTrue(TimeExtractor::isTimeOnly("  15:30\n"));
    }

    public function testIsTimeOnlyReturnsFalseWhenTimeHasSurroundingText(): void
    {
        $this->assertFalse(TimeExtractor::isTimeOnly('Game 18:00'));
    }

    public function testIsTimeOnlyReturnsFalseForSentenceEndingInTime(): void
    {
        $this->assertFalse(TimeExtractor::isTimeOnly('Bla bla bla 10:00'));
    }

    public function testIsTimeOnlyReturnsFalseWhenTimeHasTrailingText(): void
    {
        $this->assertFalse(TimeExtractor::isTimeOnly('18:00 please'));
    }

    public function testIsTimeOnlyReturnsFalseForEmptyString(): void
    {
        $this->assertFalse(TimeExtractor::isTimeOnly(''));
    }

    public function testIsTimeOnlyReturnsFalseForTextWithoutTime(): void
    {
        $this->assertFalse(TimeExtractor::isTimeOnly('hello'));
    }
}