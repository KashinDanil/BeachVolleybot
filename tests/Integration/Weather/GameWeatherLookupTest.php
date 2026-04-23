<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Weather;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Weather\Forecast\Cache\WeatherCacheManager;
use BeachVolleybot\Weather\Forecast\GameWeatherLookup\GameWeatherLookup;
use BeachVolleybot\Weather\Forecast\Models\WeatherHour;
use BeachVolleybot\Weather\Forecast\Models\WeatherSnapshot;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use DateTimeImmutable;
use DateTimeZone;

final class GameWeatherLookupTest extends DatabaseTestCase
{
    private WeatherCacheManager $weatherCache;

    private GameWeatherLookup $lookup;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = file_get_contents(__DIR__ . '/../../../migrations/003_create_weather_tables.sql');
        $this->db->pdo->exec($schema);
        Connection::set($this->db);

        $this->weatherCache = new WeatherCacheManager();
        $this->lookup = new GameWeatherLookup();
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    public function testReturnsNullWhenTitleHasNoTime(): void
    {
        // Without a kickoff time in the title we can't compute a cache key,
        // so the lookup bails regardless of whether rows exist.
        $game = $this->game('Beach Saturday', '41.397,2.211');

        $this->assertNull($this->lookup->find($game));
    }

    public function testReturnsNullWhenCacheHasNoRow(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days');
        $game = $this->game('Beach ' . $kickoffDay->format('d.m.Y') . ' 18:00', '41.397,2.211');

        $this->assertNull($this->lookup->find($game));
    }

    public function testReturnsRowEvenWhenKickoffIsInThePast(): void
    {
        // Cache seeded for a past kickoff — display must still surface it.
        // Fetching is gated by WeatherWindowResolver, but display isn't.
        $kickoffDay = new DateTimeImmutable('-1 day');
        $kickoffUtc = $this->kickoffUtc($kickoffDay, 18);
        $this->weatherCache->save(
            new LocationCoordinates(41.397, 2.211),
            $kickoffUtc,
            $this->snapshotForHour($kickoffUtc),
        );
        $game = $this->game('Beach ' . $kickoffDay->format('d.m.Y') . ' 18:00', '41.397,2.211');

        $result = $this->lookup->find($game);

        $this->assertNotNull($result);
        $this->assertSame('18:00', $result->kickoffHour->format('H:i'));
    }

    public function testReturnsRowAndKickoffHourWhenCacheHasRow(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days');
        $kickoffUtc = $this->kickoffUtc($kickoffDay, 18);
        $this->weatherCache->save(
            new LocationCoordinates(41.397, 2.211),
            $kickoffUtc,
            $this->snapshotForHour($kickoffUtc),
        );
        $game = $this->game('Beach ' . $kickoffDay->format('d.m.Y') . ' 18:00', '41.397,2.211');

        $result = $this->lookup->find($game);

        $this->assertNotNull($result);
        $this->assertSame(41.397, $result->row->coordinates->latitude);
        $this->assertSame(2.211, $result->row->coordinates->longitude);
        $this->assertSame(22.0, $result->row->snapshot->hours[0]->temperatureC);
        $this->assertSame('18:00', $result->kickoffHour->format('H:i'));
    }

    public function testRoundsCoordinatesBeforeCacheLookup(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days');
        $kickoffUtc = $this->kickoffUtc($kickoffDay, 18);
        $this->weatherCache->save(
            new LocationCoordinates(41.397, 2.211),
            $kickoffUtc,
            $this->snapshotForHour($kickoffUtc),
        );
        // Game location's exact coords round to the seeded row.
        $game = $this->game('Beach ' . $kickoffDay->format('d.m.Y') . ' 18:00', '41.3971,2.2112');

        $result = $this->lookup->find($game);

        $this->assertNotNull($result);
        $this->assertSame(41.397, $result->row->coordinates->latitude);
    }

    public function testDifferentKickoffHourDoesNotHitCacheRow(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days');
        $this->weatherCache->save(
            new LocationCoordinates(41.397, 2.211),
            $this->kickoffUtc($kickoffDay, 18),
            $this->snapshotForHour($this->kickoffUtc($kickoffDay, 18)),
        );
        // Same day + beach but 10:00 kickoff — different forecast_ts key.
        $game = $this->game('Beach ' . $kickoffDay->format('d.m.Y') . ' 10:00', '41.397,2.211');

        $this->assertNull($this->lookup->find($game));
    }

    private function game(string $title, string $location): Game
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

    private function kickoffUtc(DateTimeImmutable $kickoffDay, int $hour): DateTimeImmutable
    {
        return new DateTimeImmutable(
            $kickoffDay->format('Y-m-d') . ' ' . str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00:00',
            new DateTimeZone('UTC'),
        );
    }

    private function snapshotForHour(DateTimeImmutable $hour): WeatherSnapshot
    {
        return new WeatherSnapshot([
            new WeatherHour(
                hour: $hour,
                temperatureC: 22.0,
                weatherCode: 0,
                windMetersPerSecond: 3.0,
                windDirectionDegrees: 0,
            ),
        ]);
    }
}
