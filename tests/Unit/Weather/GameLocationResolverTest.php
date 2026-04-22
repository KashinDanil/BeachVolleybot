<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather;

use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Tests\Stub\FakeGeocodingClient;
use BeachVolleybot\Weather\DefaultLocationCoordinates;
use BeachVolleybot\Weather\GameLocationResolver;
use BeachVolleybot\Weather\LocationCoordinates;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GameLocationResolverTest extends TestCase
{
    private GameLocationResolver $resolver;

    private FakeGeocodingClient $geocodingClient;

    protected function setUp(): void
    {
        $this->geocodingClient = new FakeGeocodingClient();
        $this->resolver = new GameLocationResolver($this->geocodingClient);
    }

    public function testExplicitCoordinatesInGameLocationWin(): void
    {
        $game = $this->makeGame(title: 'Bogatell 18:30', location: '40.0,-3.0');

        $coordinates = $this->resolver->resolve($game);

        $this->assertSame(40.0, $coordinates->latitude);
        $this->assertSame(-3.0, $coordinates->longitude);
        $this->assertSame([], $this->geocodingClient->queries);
    }

    public function testExplicitCoordinatesWinEvenWhenGeocoderCouldResolveVenue(): void
    {
        $this->geocodingClient->responses = ['Bogatell' => new LocationCoordinates(41.397, 2.211)];

        $game = $this->makeGame(title: 'Bogatell 18:30', location: '40.0,-3.0');

        $coordinates = $this->resolver->resolve($game);

        $this->assertSame(40.0, $coordinates->latitude);
        $this->assertSame(-3.0, $coordinates->longitude);
        $this->assertSame([], $this->geocodingClient->queries);
    }

    public function testVenueCoordinatesResolvedViaGeocoderWhenLocationAbsent(): void
    {
        $this->geocodingClient->responses = ['Bogatell' => new LocationCoordinates(41.397, 2.211)];

        $game = $this->makeGame(title: 'Bogatell 18:30');

        $coordinates = $this->resolver->resolve($game);

        $this->assertSame(41.397, $coordinates->latitude);
        $this->assertSame(2.211, $coordinates->longitude);
        $this->assertSame(['Bogatell'], $this->geocodingClient->queries);
    }

    public function testFallsBackToDefaultWhenGeocoderReturnsNull(): void
    {
        $game = $this->makeGame(title: 'Bogatell 18:30');

        $coordinates = $this->resolver->resolve($game);

        $this->assertInstanceOf(DefaultLocationCoordinates::class, $coordinates);
        $this->assertSame(['Bogatell'], $this->geocodingClient->queries);
    }

    public function testFallsBackToDefaultWhenTitleHasNoVenue(): void
    {
        $game = $this->makeGame(title: '18:30');

        $coordinates = $this->resolver->resolve($game);

        $this->assertInstanceOf(DefaultLocationCoordinates::class, $coordinates);
        $this->assertSame([], $this->geocodingClient->queries);
    }

    public function testUnparseableLocationFallsThroughToVenueExtraction(): void
    {
        $this->geocodingClient->responses = ['Bogatell' => new LocationCoordinates(41.397, 2.211)];

        $game = $this->makeGame(title: 'Bogatell 18:30', location: 'not-a-coord');

        $coordinates = $this->resolver->resolve($game);

        $this->assertSame(41.397, $coordinates->latitude);
        $this->assertSame(['Bogatell'], $this->geocodingClient->queries);
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