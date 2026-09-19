<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders;

use BeachVolleybot\Telegram\MessageBuilders\PlayersPerNetSelection;
use PHPUnit\Framework\TestCase;

final class PlayersPerNetSelectionTest extends TestCase
{
    public function testDefaultIsSix(): void
    {
        $this->assertSame(6, PlayersPerNetSelection::DEFAULT);
    }

    public function testClampsBelowTheMinimumUpToFour(): void
    {
        $this->assertSame(4, PlayersPerNetSelection::pending(0)->value());
        $this->assertSame(4, PlayersPerNetSelection::pending(-5)->value());
    }

    public function testClampsAboveTheCeilingDownToTwelve(): void
    {
        $this->assertSame(12, PlayersPerNetSelection::pending(20)->value());
    }

    public function testCanDecreaseIsFalseOnlyAtTheMinimum(): void
    {
        $this->assertFalse(PlayersPerNetSelection::pending(4)->canDecrease());
        $this->assertTrue(PlayersPerNetSelection::pending(5)->canDecrease());
    }

    public function testCanIncreaseIsFalseOnlyAtTheCeiling(): void
    {
        $this->assertFalse(PlayersPerNetSelection::pending(12)->canIncrease());
        $this->assertTrue(PlayersPerNetSelection::pending(11)->canIncrease());
    }

    public function testDecreasedAndIncreasedShiftByOneAndKeepTheAppliedState(): void
    {
        $applied = PlayersPerNetSelection::applied(6);

        $this->assertSame(5, $applied->decreased()->value());
        $this->assertTrue($applied->decreased()->isApplied());
        $this->assertSame(7, $applied->increased()->value());
        $this->assertTrue($applied->increased()->isApplied());
    }

    public function testAppliedSelectionReportsItsValue(): void
    {
        $selection = PlayersPerNetSelection::applied(8);

        $this->assertTrue($selection->isApplied());
        $this->assertSame(8, $selection->appliedValue());
        $this->assertSame(8, $selection->value());
    }

    public function testPendingSelectionHasNoAppliedValue(): void
    {
        $selection = PlayersPerNetSelection::pending(8);

        $this->assertFalse($selection->isApplied());
        $this->assertNull($selection->appliedValue());
        $this->assertSame(8, $selection->value());
    }
}
