<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game;

use BeachVolleybot\Game\GameLabel;
use PHPUnit\Framework\TestCase;

final class GameLabelTest extends TestCase
{
    public function testIncludesGameIdDateAndTime(): void
    {
        $this->assertSame('#42 Saturday 18:00', GameLabel::format(42, 'Saturday 18:00 Bogatell'));
    }

    public function testNormalizesShortTime(): void
    {
        $this->assertSame('#1 Saturday 09:30', GameLabel::format(1, 'Saturday 9:30'));
    }

    public function testFallsBackToIdOnlyWhenTitleHasNeitherDateNorTime(): void
    {
        $this->assertSame('#7', GameLabel::format(7, 'Casual session'));
    }

    public function testIncludesOnlyDateWhenTimeMissing(): void
    {
        $this->assertSame('#3 Saturday', GameLabel::format(3, 'Saturday'));
    }

    public function testIncludesOnlyTimeWhenDateMissing(): void
    {
        $this->assertSame('#4 18:00', GameLabel::format(4, '18:00'));
    }

    public function testHandlesNumericDateFormat(): void
    {
        $this->assertSame('#9 11.04 10:00', GameLabel::format(9, '11.04 Bogatell 10:00'));
    }
}
