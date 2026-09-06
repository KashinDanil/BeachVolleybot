<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather;

use BeachVolleybot\Weather\Location\GameLocationResolver;
use BeachVolleybot\Weather\Location\Models\DefaultLocationCoordinates;
use PHPUnit\Framework\TestCase;

final class GameLocationResolverTest extends TestCase
{
    private GameLocationResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new GameLocationResolver();
    }

    public function testExplicitCoordinatesInGameLocationWin(): void
    {
        $coordinates = $this->resolver->resolve('40.0,-3.0', null, 'Bogatell 18:30');

        $this->assertSame(40.0, $coordinates->latitude);
        $this->assertSame(-3.0, $coordinates->longitude);
    }

    public function testExplicitCoordinatesWinEvenWhenTitleContainsKnownVenue(): void
    {
        $coordinates = $this->resolver->resolve('40.0,-3.0', null, 'Bogatell 18:30');

        $this->assertSame(40.0, $coordinates->latitude);
        $this->assertSame(-3.0, $coordinates->longitude);
    }

    public function testVenueFromTitleResolvesViaWhitelist(): void
    {
        $coordinates = $this->resolver->resolve(null, null, 'Bogatell 18:30');

        $this->assertSame(41.394, $coordinates->latitude);
        $this->assertSame(2.208, $coordinates->longitude);
    }

    public function testFallsBackToDefaultWhenTitleHasNoKnownVenue(): void
    {
        $coordinates = $this->resolver->resolve(null, null, 'Friday 18:30');

        $this->assertInstanceOf(DefaultLocationCoordinates::class, $coordinates);
    }

    public function testUnparseableLocationFallsThroughToWhitelist(): void
    {
        $coordinates = $this->resolver->resolve('not-a-coord', null, 'Bogatell 18:30');

        $this->assertSame(41.394, $coordinates->latitude);
    }

    public function testVenueColumnIsUsedWhenPresent(): void
    {
        $coordinates = $this->resolver->resolve(null, 'Bogatell', 'Beach volley 18:30');

        $this->assertSame(41.394, $coordinates->latitude);
    }

    public function testVenueColumnWinsOverADifferentVenueNamedInTheTitle(): void
    {
        $this->assertSame(41.394, $this->resolver->resolve(null, 'Bogatell', 'Somorrostro 18:30')->latitude);
    }

    public function testFallsBackToTitleScanWhenVenueColumnIsEmpty(): void
    {
        $this->assertSame(41.383, $this->resolver->resolve(null, null, 'Somorrostro 18:30')->latitude);
    }

    public function testExplicitCoordinatesWinOverTheVenueColumn(): void
    {
        $this->assertSame(40.0, $this->resolver->resolve('40.0,-3.0', 'Bogatell', 'Beach volley 18:30')->latitude);
    }
}
