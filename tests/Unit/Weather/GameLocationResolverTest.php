<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Weather\Location\GameLocationResolver;
use BeachVolleybot\Weather\Location\Models\DefaultLocationCoordinates;
use DateTimeImmutable;
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
        $game = $this->makeGame(title: 'Bogatell 18:30', location: '40.0,-3.0');

        $coordinates = $this->resolver->resolve($game);

        $this->assertSame(40.0, $coordinates->latitude);
        $this->assertSame(-3.0, $coordinates->longitude);
    }

    public function testExplicitCoordinatesWinEvenWhenTitleContainsKnownVenue(): void
    {
        $game = $this->makeGame(title: 'Bogatell 18:30', location: '40.0,-3.0');

        $coordinates = $this->resolver->resolve($game);

        $this->assertSame(40.0, $coordinates->latitude);
        $this->assertSame(-3.0, $coordinates->longitude);
    }

    public function testVenueFromTitleResolvesViaWhitelist(): void
    {
        $game = $this->makeGame(title: 'Bogatell 18:30');

        $coordinates = $this->resolver->resolve($game);

        $this->assertSame(41.394, $coordinates->latitude);
        $this->assertSame(2.208, $coordinates->longitude);
    }

    public function testFallsBackToDefaultWhenTitleHasNoKnownVenue(): void
    {
        $game = $this->makeGame(title: 'Friday 18:30');

        $coordinates = $this->resolver->resolve($game);

        $this->assertInstanceOf(DefaultLocationCoordinates::class, $coordinates);
    }

    public function testUnparseableLocationFallsThroughToWhitelist(): void
    {
        $game = $this->makeGame(title: 'Bogatell 18:30', location: 'not-a-coord');

        $coordinates = $this->resolver->resolve($game);

        $this->assertSame(41.394, $coordinates->latitude);
    }

    public function testVenueColumnIsUsedWhenPresent(): void
    {
        $game = $this->makeGame(title: 'Beach volley 18:30', venueName: 'Bogatell');

        $coordinates = $this->resolver->resolve($game);

        $this->assertSame(41.394, $coordinates->latitude);
    }

    public function testVenueColumnWinsOverADifferentVenueNamedInTheTitle(): void
    {
        $game = $this->makeGame(title: 'Somorrostro 18:30', venueName: 'Bogatell');

        $this->assertSame(41.394, $this->resolver->resolve($game)->latitude);
    }

    public function testFallsBackToTitleScanWhenVenueColumnIsEmpty(): void
    {
        $game = $this->makeGame(title: 'Somorrostro 18:30');

        $this->assertSame(41.383, $this->resolver->resolve($game)->latitude);
    }

    public function testExplicitCoordinatesWinOverTheVenueColumn(): void
    {
        $game = $this->makeGame(title: 'Beach volley 18:30', location: '40.0,-3.0', venueName: 'Bogatell');

        $this->assertSame(40.0, $this->resolver->resolve($game)->latitude);
    }

    private function makeGame(string $title, ?string $location = null, ?string $venueName = null): Game
    {
        $game = new Game(
            gameId: 1,
            gameKey: 'iq',
            messageTargets: [],
            title: $title,
            users: [],
            createdAt: new DateTimeImmutable(),
            kickoffAt: GameDateTimeResolver::resolve($title, new DateTimeImmutable()) ?? new DateTimeImmutable('2099-12-31 18:00:00'),
            venueName: $venueName,
            location: $location,
        );
        $game->init();

        return $game;
    }
}
