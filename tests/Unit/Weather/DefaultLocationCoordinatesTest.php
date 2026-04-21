<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather;

use BeachVolleybot\Weather\DefaultLocationCoordinates;
use BeachVolleybot\Weather\LocationCoordinates;
use PHPUnit\Framework\TestCase;

final class DefaultLocationCoordinatesTest extends TestCase
{
    public function testHoldsPlayaDeBogatellCoordinates(): void
    {
        $coordinates = new DefaultLocationCoordinates();

        $this->assertSame(41.3942, $coordinates->latitude);
        $this->assertSame(2.2071, $coordinates->longitude);
    }

    public function testIsInstanceOfLocationCoordinates(): void
    {
        $coordinates = new DefaultLocationCoordinates();

        $this->assertInstanceOf(LocationCoordinates::class, $coordinates);
    }

    public function testInheritsRoundedMethod(): void
    {
        $rounded = new DefaultLocationCoordinates()->rounded();

        $this->assertSame(41.394, $rounded->latitude);
        $this->assertSame(2.207, $rounded->longitude);
    }

    public function testRoundedStaysIdempotent(): void
    {
        $once = new DefaultLocationCoordinates()->rounded();
        $twice = $once->rounded();

        $this->assertSame($once->latitude, $twice->latitude);
        $this->assertSame($once->longitude, $twice->longitude);
    }
}
