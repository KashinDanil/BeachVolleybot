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

    public function testExtractsEnglishDayMonth(): void
    {
        $this->assertSame('11 April', DateExtractor::extract('Game 11 April 18:00'));
    }

    public function testExtractsEnglishMonthDay(): void
    {
        $this->assertSame('April 11', DateExtractor::extract('Game April 11 18:00'));
    }

    public function testExtractsEnglishShortMonth(): void
    {
        $this->assertSame('11 Apr', DateExtractor::extract('Game 11 Apr'));
    }

    public function testExtractsRussianGenitiveMonth(): void
    {
        $this->assertSame('11 апреля', DateExtractor::extract('Игра 11 апреля 18:00'));
    }

    public function testExtractsRussianNominativeMonth(): void
    {
        $this->assertSame('11 апрель', DateExtractor::extract('Игра 11 апрель'));
    }

    public function testExtractsRussianShortMonth(): void
    {
        $this->assertSame('11 апр', DateExtractor::extract('Игра 11 апр'));
    }

    public function testExtractsSpanishWithDe(): void
    {
        $this->assertSame('11 de abril', DateExtractor::extract('Juego 11 de abril 18:00'));
    }

    public function testExtractsSpanishWithoutDe(): void
    {
        $this->assertSame('11 abril', DateExtractor::extract('Juego 11 abril'));
    }

    public function testExtractsSpanishMonthDay(): void
    {
        $this->assertSame('abril 11', DateExtractor::extract('Juego abril 11'));
    }

    public function testTextMonthIsCaseInsensitive(): void
    {
        $this->assertSame('11 APRIL', DateExtractor::extract('Game 11 APRIL'));
    }

    public function testExtractsMonthDayWithOrdinalTh(): void
    {
        $this->assertSame('April 10th', DateExtractor::extract('Game April 10th'));
    }

    public function testExtractsMonthDayWithOrdinalRd(): void
    {
        $this->assertSame('March 3rd', DateExtractor::extract('Game March 3rd'));
    }

    public function testExtractsMonthDayWithOrdinalSt(): void
    {
        $this->assertSame('January 1st', DateExtractor::extract('Game January 1st'));
    }

    public function testExtractsMonthDayWithOrdinalNd(): void
    {
        $this->assertSame('February 2nd', DateExtractor::extract('Game February 2nd'));
    }

    public function testExtractsOrdinalDayMonth(): void
    {
        $this->assertSame('3rd April', DateExtractor::extract('Game 3rd April'));
    }

    public function testExtractsOrdinalDayOfMonth(): void
    {
        $this->assertSame('1st of March', DateExtractor::extract('Game 1st of March'));
    }

    public function testNumericPatternTakesPriority(): void
    {
        $this->assertSame('11.04', DateExtractor::extract('11.04 April'));
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
