<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common;

use BeachVolleybot\Common\DayOfWeekExtractor;
use PHPUnit\Framework\TestCase;

final class DayOfWeekExtractorTest extends TestCase
{
    public function testExtractsDayFromTitle(): void
    {
        $this->assertSame('Friday', DayOfWeekExtractor::extract('Friday Game 18:00'));
    }

    public function testExtractsCaseInsensitive(): void
    {
        $this->assertSame('saturday', DayOfWeekExtractor::extract('saturday Game'));
    }

    public function testExtractsFirstDayWhenMultiple(): void
    {
        $this->assertSame('Monday', DayOfWeekExtractor::extract('Monday or Tuesday'));
    }

    public function testExtractsDayAtStartOfString(): void
    {
        $this->assertSame('Sunday', DayOfWeekExtractor::extract('Sunday'));
    }

    public function testReturnsNullWhenNoDay(): void
    {
        $this->assertNull(DayOfWeekExtractor::extract('Beach Game 18:00'));
    }

    public function testReturnsNullForEmptyString(): void
    {
        $this->assertNull(DayOfWeekExtractor::extract(''));
    }

    public function testDoesNotMatchPartialDayName(): void
    {
        $this->assertNull(DayOfWeekExtractor::extract('Sundays are fun'));
    }
}
