<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game\Roster;

use BeachVolleybot\Game\Roster\Position;
use BeachVolleybot\Game\Roster\PositionRange;
use PHPUnit\Framework\TestCase;

final class PositionTest extends TestCase
{
    public function testSinglePositionFormatsAsItsNumber(): void
    {
        $this->assertSame('7', new Position(7)->format());
    }

    public function testRangeFormatsAsBounds(): void
    {
        $this->assertSame('4-7', new PositionRange(4, 7)->format());
    }

    public function testSinglePositionCoversOneSlot(): void
    {
        $this->assertSame(1, new Position(7)->slotCount());
    }

    public function testRangeSlotCountCountsBothBounds(): void
    {
        $this->assertSame(4, new PositionRange(4, 7)->slotCount());
        $this->assertSame(2, new PositionRange(1, 2)->slotCount());
    }

    public function testSinglePositionSpansItself(): void
    {
        $position = new Position(7);

        $this->assertSame(7, $position->first());
        $this->assertSame(7, $position->last());
    }

    public function testRangeSpansItsBounds(): void
    {
        $range = new PositionRange(4, 7);

        $this->assertSame(4, $range->first());
        $this->assertSame(7, $range->last());
    }
}
