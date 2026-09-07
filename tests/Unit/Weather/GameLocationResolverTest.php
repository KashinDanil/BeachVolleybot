<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather;

use BeachVolleybot\Weather\Location\GameLocationResolver;
use BeachVolleybot\Weather\Location\KnownVenues;
use PHPUnit\Framework\TestCase;

final class GameLocationResolverTest extends TestCase
{
    private GameLocationResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new GameLocationResolver();
    }

    public function testExplicitCoordinatesWinOverTheVenue(): void
    {
        $coordinates = $this->resolver->resolve('40.0,-3.0', 'Bogatell');

        $this->assertSame(40.0, $coordinates->latitude);
        $this->assertSame(-3.0, $coordinates->longitude);
    }

    public function testVenueColumnResolvesViaTheCatalog(): void
    {
        $coordinates = $this->resolver->resolve(null, 'Somorrostro');

        $this->assertSame(41.383, $coordinates->latitude);
        $this->assertSame(2.198, $coordinates->longitude);
    }

    public function testUnparseableLocationFallsThroughToTheVenue(): void
    {
        $this->assertSame(41.394, $this->resolver->resolve('not-a-coord', 'Bogatell')->latitude);
    }

    public function testEmptyVenueFallsBackToTheDefaultVenue(): void
    {
        $this->assertSame(KnownVenues::defaultVenue()->coordinates, $this->resolver->resolve(null, null));
    }

    public function testVenueUnknownToTheCatalogFallsBackToTheDefaultVenue(): void
    {
        $this->assertSame(KnownVenues::defaultVenue()->coordinates, $this->resolver->resolve(null, 'Copacabana'));
    }
}
