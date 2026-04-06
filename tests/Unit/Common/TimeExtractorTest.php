<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common;

use BeachVolleybot\Common\TimeExtractor;
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
}