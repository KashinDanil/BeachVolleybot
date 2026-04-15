<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common;

use BeachVolleybot\Common\LocationUpdateThrottle;
use PHPUnit\Framework\TestCase;

final class LocationUpdateThrottleTest extends TestCase
{
    public function testFirstCheckIsNotThrottled(): void
    {
        $throttle = new LocationUpdateThrottle();

        $this->assertFalse($throttle->isThrottled('query_1'));
    }

    public function testIsThrottledAfterTouch(): void
    {
        $throttle = new LocationUpdateThrottle();

        $throttle->touch('query_1');

        $this->assertTrue($throttle->isThrottled('query_1'));
    }

    public function testDifferentKeysAreIndependent(): void
    {
        $throttle = new LocationUpdateThrottle();

        $throttle->touch('query_1');

        $this->assertTrue($throttle->isThrottled('query_1'));
        $this->assertFalse($throttle->isThrottled('query_2'));
    }

    public function testOldestEntryIsEvictedWhenCapacityExceeded(): void
    {
        $throttle = new LocationUpdateThrottle();

        for ($i = 1; $i <= 101; $i++) {
            $throttle->touch("query_$i");
        }

        $this->assertFalse($throttle->isThrottled('query_1'));
        $this->assertTrue($throttle->isThrottled('query_50'));
        $this->assertTrue($throttle->isThrottled('query_101'));
    }
}
