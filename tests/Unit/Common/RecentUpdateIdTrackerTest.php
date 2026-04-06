<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Common;

use BeachVolleybot\Common\RecentUpdateIdTracker;
use PHPUnit\Framework\TestCase;

final class RecentUpdateIdTrackerTest extends TestCase
{
    public function testNewIdIsNotTracked(): void
    {
        $tracker = new RecentUpdateIdTracker();

        $this->assertFalse($tracker->isTracked(1));
    }

    public function testIdIsTrackedAfterFirstCheck(): void
    {
        $tracker = new RecentUpdateIdTracker();

        $tracker->isTracked(1);

        $this->assertTrue($tracker->isTracked(1));
    }

    public function testMultipleDistinctIdsAreTrackedIndependently(): void
    {
        $tracker = new RecentUpdateIdTracker();

        $this->assertFalse($tracker->isTracked(1));
        $this->assertFalse($tracker->isTracked(2));
        $this->assertFalse($tracker->isTracked(3));
    }

    public function testOldestIdIsEvictedWhenCapacityExceeded(): void
    {
        $tracker = new RecentUpdateIdTracker();

        for ($i = 1; $i <= 101; $i++) {
            $tracker->isTracked($i);
        }

        // id=101 still tracked
        $this->assertTrue($tracker->isTracked(101));
        // id=50 still tracked
        $this->assertTrue($tracker->isTracked(50));
        // id=1 was evicted
        $this->assertFalse($tracker->isTracked(1));
    }
}