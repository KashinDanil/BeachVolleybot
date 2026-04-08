<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common;

use BeachVolleybot\Common\DateExtractor;
use PHPUnit\Framework\TestCase;

final class DateExtractorTest extends TestCase
{
    public function testExtractsShortDate(): void
    {
        $this->assertSame('11.04', DateExtractor::extract('Game 11.04 18:00'));
    }

    public function testExtractsDateWithYear(): void
    {
        $this->assertSame('11.04.2026', DateExtractor::extract('Game 11.04.2026'));
    }

    public function testExtractsDateWithShortYear(): void
    {
        $this->assertSame('11.04.26', DateExtractor::extract('Game 11.04.26'));
    }

    public function testExtractsSingleDigitDay(): void
    {
        $this->assertSame('1.04', DateExtractor::extract('Game 1.04'));
    }

    public function testExtractsFirstDateWhenMultiple(): void
    {
        $this->assertSame('11.04', DateExtractor::extract('11.04 or 12.04'));
    }

    public function testReturnsNullWhenNoDate(): void
    {
        $this->assertNull(DateExtractor::extract('Friday Game 18:00'));
    }

    public function testReturnsNullForEmptyString(): void
    {
        $this->assertNull(DateExtractor::extract(''));
    }
}
