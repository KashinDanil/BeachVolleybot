<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Weather;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Weather\DefaultLocationCoordinates;
use BeachVolleybot\Weather\GameLocationResolver;
use BeachVolleybot\Weather\GeocodingCacheManager;
use BeachVolleybot\Weather\LocationCoordinates;
use DateTimeImmutable;

final class GameLocationResolverTest extends DatabaseTestCase
{
    private GameLocationResolver $resolver;

    private GeocodingCacheManager $geocodingCache;

    public function testExplicitCoordinatesInGameLocationWin(): void
    {
        $game = $this->makeGame(title: 'Bogatell 18:30', location: '40.0,-3.0');

        $coordinates = $this->resolver->resolve($game);

        $this->assertSame(40.0, $coordinates->latitude);
        $this->assertSame(-3.0, $coordinates->longitude);
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

    // --- resolve() ---

    public function testExplicitCoordinatesWinEvenWhenVenueCacheHasMatch(): void
    {
        $this->geocodingCache->save('Bogatell', new LocationCoordinates(41.397, 2.211));

        $game = $this->makeGame(title: 'Bogatell 18:30', location: '40.0,-3.0');

        $coordinates = $this->resolver->resolve($game);

        $this->assertSame(40.0, $coordinates->latitude);
        $this->assertSame(-3.0, $coordinates->longitude);
    }

    public function testCachedVenueUsedWhenLocationAbsent(): void
    {
        $this->geocodingCache->save('Bogatell', new LocationCoordinates(41.397, 2.211));

        $game = $this->makeGame(title: 'Bogatell 18:30');

        $coordinates = $this->resolver->resolve($game);

        $this->assertSame(41.397, $coordinates->latitude);
        $this->assertSame(2.211, $coordinates->longitude);
    }

    public function testFallsBackToDefaultWhenCacheEmptyAndNoLocation(): void
    {
        $game = $this->makeGame(title: 'Bogatell 18:30');

        $coordinates = $this->resolver->resolve($game);

        $this->assertInstanceOf(DefaultLocationCoordinates::class, $coordinates);
    }

    public function testFallsBackToDefaultWhenCachedVenueIsAMiss(): void
    {
        $this->geocodingCache->save('Bogatell', null);

        $game = $this->makeGame(title: 'Bogatell 18:30');

        $coordinates = $this->resolver->resolve($game);

        $this->assertInstanceOf(DefaultLocationCoordinates::class, $coordinates);
    }

    public function testFallsBackToDefaultWhenTitleHasNoVenue(): void
    {
        $game = $this->makeGame(title: '18:30');

        $coordinates = $this->resolver->resolve($game);

        $this->assertInstanceOf(DefaultLocationCoordinates::class, $coordinates);
    }

    public function testUnparseableLocationFallsThroughToVenueExtraction(): void
    {
        $this->geocodingCache->save('Bogatell', new LocationCoordinates(41.397, 2.211));

        $game = $this->makeGame(title: 'Bogatell 18:30', location: 'not-a-coord');

        $coordinates = $this->resolver->resolve($game);

        $this->assertSame(41.397, $coordinates->latitude);
    }

    public function testResolveVenueQueryReturnsNullWhenExplicitCoordsPresent(): void
    {
        $game = $this->makeGame(title: 'Bogatell 18:30', location: '40.0,-3.0');

        $this->assertNull($this->resolver->resolveVenueQuery($game));
    }

    // --- resolveVenueQuery() ---

    public function testResolveVenueQueryReturnsVenueStringWhenNoLocation(): void
    {
        $game = $this->makeGame(title: 'Bogatell 18:30');

        $this->assertSame('Bogatell', $this->resolver->resolveVenueQuery($game));
    }

    public function testResolveVenueQueryReturnsNullWhenTitleHasNoVenue(): void
    {
        $game = $this->makeGame(title: '18:30');

        $this->assertNull($this->resolver->resolveVenueQuery($game));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $schema = file_get_contents(__DIR__ . '/../../../migrations/003_create_weather_tables.sql');
        $this->db->pdo->exec($schema);

        Connection::set($this->db);
        $this->geocodingCache = new GeocodingCacheManager();
        $this->resolver = new GameLocationResolver($this->geocodingCache);
    }

    protected function tearDown(): void
    {
        Connection::close();
    }
}
