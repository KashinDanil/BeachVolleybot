<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common;

use BeachVolleybot\Tests\Unit\Common\Stub\StubBitFlag;
use BeachVolleybot\Tests\Unit\Common\Stub\StubBitmask;
use PHPUnit\Framework\TestCase;

final class AbstractBitmaskTest extends TestCase
{
    public function testDefaultMaskHasEveryFlagUnset(): void
    {
        $mask = new StubBitmask();

        $this->assertFalse($mask->has(StubBitFlag::First));
        $this->assertFalse($mask->has(StubBitFlag::Second));
        $this->assertSame(0, $mask->toInt());
    }

    public function testWithBitSetsExactlyThatFlagAndLeavesTheOriginalAlone(): void
    {
        $original = new StubBitmask();

        $updated = $original->with(StubBitFlag::First);

        $this->assertFalse($original->has(StubBitFlag::First));
        $this->assertTrue($updated->has(StubBitFlag::First));
        $this->assertFalse($updated->has(StubBitFlag::Second));
    }

    public function testWithoutBitClearsTheFlag(): void
    {
        $set = new StubBitmask()->with(StubBitFlag::First);

        $cleared = $set->without(StubBitFlag::First);

        $this->assertFalse($cleared->has(StubBitFlag::First));
    }

    public function testBitsAreIndependent(): void
    {
        $mask = new StubBitmask()->with(StubBitFlag::First)->with(StubBitFlag::Second);

        $this->assertTrue($mask->has(StubBitFlag::First));
        $this->assertTrue($mask->has(StubBitFlag::Second));

        $mask = $mask->without(StubBitFlag::First);

        $this->assertFalse($mask->has(StubBitFlag::First));
        $this->assertTrue($mask->has(StubBitFlag::Second));
    }

    public function testFromIntAndToIntRoundTrip(): void
    {
        $rawMask = StubBitFlag::First->bit() | StubBitFlag::Second->bit();

        $mask = StubBitmask::fromInt($rawMask);

        $this->assertSame($rawMask, $mask->toInt());
        $this->assertTrue($mask->has(StubBitFlag::First));
        $this->assertTrue($mask->has(StubBitFlag::Second));
    }

    public function testLateStaticBindingReturnsTheChildTypeFromEveryFactory(): void
    {
        $this->assertInstanceOf(StubBitmask::class, StubBitmask::fromInt(0));
        $this->assertInstanceOf(StubBitmask::class, new StubBitmask()->with(StubBitFlag::First));
        $this->assertInstanceOf(StubBitmask::class, new StubBitmask()->with(StubBitFlag::First)->without(StubBitFlag::First));
    }
}
