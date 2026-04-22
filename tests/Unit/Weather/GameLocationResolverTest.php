<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather;

use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Tests\Stub\FakeLocationResolver;
use BeachVolleybot\Weather\Location\GameLocationResolver;
use BeachVolleybot\Weather\Location\Models\DefaultLocationCoordinates;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GameLocationResolverTest extends TestCase
{
    private GameLocationResolver $resolver;

    private FakeLocationResolver $locationResolver;

    protected function setUp(): void
    {
        $this->locationResolver = new FakeLocationResolver();
        $this->resolver = new GameLocationResolver($this->locationResolver);
    }

    public function testExplicitCoordinatesInGameLocationWin(): void
    {
        $game = $this->makeGame(title: 'Bogatell 18:30', location: '40.0,-3.0');

        $coordinates = $this->resolver->resolve($game);

        $this->assertSame(40.0, $coordinates->latitude);
        $this->assertSame(-3.0, $coordinates->longitude);
        $this->assertSame([], $this->locationResolver->queries);
    }

    public function testExplicitCoordinatesWinEvenWhenGeocoderCouldResolveVenue(): void
    {
        $this->locationResolver->responses = ['Bogatell' => new LocationCoordinates(41.397, 2.211)];

        $game = $this->makeGame(title: 'Bogatell 18:30', location: '40.0,-3.0');

        $coordinates = $this->resolver->resolve($game);

        $this->assertSame(40.0, $coordinates->latitude);
        $this->assertSame(-3.0, $coordinates->longitude);
        $this->assertSame([], $this->locationResolver->queries);
    }

    public function testVenueCoordinatesResolvedViaGeocoderWhenLocationAbsent(): void
    {
        $this->locationResolver->responses = ['Bogatell' => new LocationCoordinates(41.397, 2.211)];

        $game = $this->makeGame(title: 'Bogatell 18:30');

        $coordinates = $this->resolver->resolve($game);

        $this->assertSame(41.397, $coordinates->latitude);
        $this->assertSame(2.211, $coordinates->longitude);
        $this->assertSame(['Bogatell'], $this->locationResolver->queries);
    }

    public function testFallsBackToDefaultWhenGeocoderReturnsNull(): void
    {
        $game = $this->makeGame(title: 'Bogatell 18:30');

        $coordinates = $this->resolver->resolve($game);

        $this->assertInstanceOf(DefaultLocationCoordinates::class, $coordinates);
        $this->assertSame(['Bogatell'], $this->locationResolver->queries);
    }

    public function testFallsBackToDefaultWhenTitleHasNoVenue(): void
    {
        $game = $this->makeGame(title: '18:30');

        $coordinates = $this->resolver->resolve($game);

        $this->assertInstanceOf(DefaultLocationCoordinates::class, $coordinates);
        $this->assertSame([], $this->locationResolver->queries);
    }

    public function testUnparseableLocationFallsThroughToVenueExtraction(): void
    {
        $this->locationResolver->responses = ['Bogatell' => new LocationCoordinates(41.397, 2.211)];

        $game = $this->makeGame(title: 'Bogatell 18:30', location: 'not-a-coord');

        $coordinates = $this->resolver->resolve($game);

        $this->assertSame(41.397, $coordinates->latitude);
        $this->assertSame(['Bogatell'], $this->locationResolver->queries);
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