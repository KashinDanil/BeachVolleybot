<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather;

use BeachVolleybot\Weather\LocationCoordinates;
use PHPUnit\Framework\TestCase;

final class LocationCoordinatesTest extends TestCase
{
    // --- tryParse happy path ---

    public function testParsesWellFormedCoordinates(): void
    {
        $coordinates = LocationCoordinates::tryParse('41.3942,2.2071');

        $this->assertNotNull($coordinates);
        $this->assertSame(41.3942, $coordinates->latitude);
        $this->assertSame(2.2071, $coordinates->longitude);
    }

    public function testParsesCoordinatesWithSurroundingWhitespace(): void
    {
        $coordinates = LocationCoordinates::tryParse('  41.3942 ,  2.2071  ');

        $this->assertNotNull($coordinates);
        $this->assertSame(41.3942, $coordinates->latitude);
        $this->assertSame(2.2071, $coordinates->longitude);
    }

    public function testParsesNegativeCoordinates(): void
    {
        $coordinates = LocationCoordinates::tryParse('-33.8688,-70.6693');

        $this->assertNotNull($coordinates);
        $this->assertSame(-33.8688, $coordinates->latitude);
        $this->assertSame(-70.6693, $coordinates->longitude);
    }

    public function testParsesIntegerCoordinates(): void
    {
        $coordinates = LocationCoordinates::tryParse('41,2');

        $this->assertNotNull($coordinates);
        $this->assertSame(41.0, $coordinates->latitude);
        $this->assertSame(2.0, $coordinates->longitude);
    }

    // --- tryParse null / empty / junk ---

    public function testReturnsNullForNullInput(): void
    {
        $this->assertNull(LocationCoordinates::tryParse(null));
    }

    public function testReturnsNullForEmptyString(): void
    {
        $this->assertNull(LocationCoordinates::tryParse(''));
    }

    public function testReturnsNullForSingleValue(): void
    {
        $this->assertNull(LocationCoordinates::tryParse('41.3942'));
    }

    public function testReturnsNullForTooManySegments(): void
    {
        $this->assertNull(LocationCoordinates::tryParse('41.3942,2.2071,99'));
    }

    public function testReturnsNullForNonNumericLatitude(): void
    {
        $this->assertNull(LocationCoordinates::tryParse('abc,2.2071'));
    }

    public function testReturnsNullForNonNumericLongitude(): void
    {
        $this->assertNull(LocationCoordinates::tryParse('41.3942,xyz'));
    }

    // --- tryParse range validation ---

    public function testReturnsNullForLatitudeAboveRange(): void
    {
        $this->assertNull(LocationCoordinates::tryParse('90.1,0'));
    }

    public function testReturnsNullForLatitudeBelowRange(): void
    {
        $this->assertNull(LocationCoordinates::tryParse('-90.1,0'));
    }

    public function testReturnsNullForLongitudeAboveRange(): void
    {
        $this->assertNull(LocationCoordinates::tryParse('0,180.1'));
    }

    public function testReturnsNullForLongitudeBelowRange(): void
    {
        $this->assertNull(LocationCoordinates::tryParse('0,-180.1'));
    }

    public function testAcceptsLatitudeAtExactBoundary(): void
    {
        $this->assertNotNull(LocationCoordinates::tryParse('90,180'));
        $this->assertNotNull(LocationCoordinates::tryParse('-90,-180'));
    }

    // --- rounded ---

    public function testRoundingCollapsesNearbyCoordinatesToSameValue(): void
    {
        // Both values sit on the same side of the 3rd-decimal boundary,
        // so they round to the same grid cell (≈110 m tolerance).
        $nearby1 = new LocationCoordinates(41.39721, 2.21082)->rounded();
        $nearby2 = new LocationCoordinates(41.39742, 2.21093)->rounded();

        $this->assertSame($nearby1->latitude, $nearby2->latitude);
        $this->assertSame($nearby1->longitude, $nearby2->longitude);
    }

    public function testRoundingUsesThreeDecimals(): void
    {
        $rounded = new LocationCoordinates(41.39651234, 2.21078999)->rounded();

        $this->assertSame(41.397, $rounded->latitude);
        $this->assertSame(2.211, $rounded->longitude);
    }

    public function testRoundingIsIdempotent(): void
    {
        $once = new LocationCoordinates(41.39651, 2.21078)->rounded();
        $twice = $once->rounded();

        $this->assertSame($once->latitude, $twice->latitude);
        $this->assertSame($once->longitude, $twice->longitude);
    }

    public function testRoundingReturnsNewInstance(): void
    {
        $original = new LocationCoordinates(41.39651, 2.21078);
        $rounded = $original->rounded();

        $this->assertNotSame($original, $rounded);
    }
}
