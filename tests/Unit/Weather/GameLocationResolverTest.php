<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather;

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

    private function makeGame(string $title, ?string $location = null): Game
    {
        $game = new Game(
            gameId: 1,
            inlineQueryId: 'iq',
            inlineMessageId: 'im',
            title: $title,
            players: [],
            createdAt: new DateTimeImmutable(),
            location: $location,
        );
        $game->init();

        return $game;
    }
}
