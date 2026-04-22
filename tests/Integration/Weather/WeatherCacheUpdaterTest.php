<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Weather;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Tests\Integration\Processors\Stub\FakeWeatherApiClient;
use BeachVolleybot\Weather\Forecast\Cache\WeatherCacheManager;
use BeachVolleybot\Weather\Forecast\Cache\WeatherCacheUpdater;
use BeachVolleybot\Weather\Forecast\Models\WeatherHour;
use BeachVolleybot\Weather\Forecast\Models\WeatherSnapshot;
use BeachVolleybot\Weather\Forecast\Models\WeatherWindow;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use DateTimeImmutable;
use DateTimeZone;

final class WeatherCacheUpdaterTest extends DatabaseTestCase
{
    private FakeWeatherApiClient $weatherClient;

    private WeatherCacheManager $cache;

    private WeatherCacheUpdater $updater;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = file_get_contents(__DIR__ . '/../../../migrations/003_create_weather_tables.sql');
        $this->db->pdo->exec($schema);

        Connection::set($this->db);

        $this->weatherClient = new FakeWeatherApiClient();
        $this->cache = new WeatherCacheManager();
        $this->updater = new WeatherCacheUpdater($this->weatherClient, $this->cache);
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    public function testColdCacheTriggersFetchAndSavesAndReturnsTrue(): void
    {
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $window = $this->buildWindow(kickoffHour: '2030-04-25 18:00:00');

        $updated = $this->updater->update($coordinates, $window, force: false);

        $this->assertTrue($updated);
        $this->assertCount(1, $this->weatherClient->calls);
        $this->assertNotNull($this->cache->find($coordinates, new DateTimeImmutable('2030-04-25 18:00:00', new DateTimeZone('UTC'))));
    }

    public function testFreshCacheShortCircuitsWithoutFetchAndReturnsFalse(): void
    {
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $kickoffUtc = new DateTimeImmutable('2030-04-25 18:00:00', new DateTimeZone('UTC'));
        $this->cache->save($coordinates, $kickoffUtc, $this->snapshot(temperature: 22.0));

        $updated = $this->updater->update(
            $coordinates,
            $this->buildWindow(kickoffHour: '2030-04-25 18:00:00'),
            force: false,
        );

        $this->assertFalse($updated);
        $this->assertSame([], $this->weatherClient->calls);
    }

    public function testForceBypassesFreshCacheAndOverwrites(): void
    {
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $kickoffUtc = new DateTimeImmutable('2030-04-25 18:00:00', new DateTimeZone('UTC'));
        $this->cache->save($coordinates, $kickoffUtc, $this->snapshot(temperature: 22.0));
        $this->weatherClient->nextSnapshot = $this->snapshot(temperature: 15.5);

        $updated = $this->updater->update(
            $coordinates,
            $this->buildWindow(kickoffHour: '2030-04-25 18:00:00'),
            force: true,
        );

        $this->assertTrue($updated);
        $this->assertCount(1, $this->weatherClient->calls);
        $row = $this->cache->find($coordinates, $kickoffUtc);
        $this->assertNotNull($row);
        $this->assertSame(15.5, $row->snapshot->hours[0]->temperatureC);
    }

    public function testExpiredCacheTriggersFetchAndReturnsTrue(): void
    {
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $kickoffUtc = new DateTimeImmutable('2030-04-25 18:00:00', new DateTimeZone('UTC'));
        $this->cache->save($coordinates, $kickoffUtc, $this->snapshot(temperature: 22.0));
        $this->db->pdo->exec(
            "UPDATE weather_cache SET fetched_at = datetime('now', '-2 hours') WHERE latitude = 41.397 AND longitude = 2.211",
        );
        $this->weatherClient->nextSnapshot = $this->snapshot(temperature: 18.0);

        $updated = $this->updater->update(
            $coordinates,
            $this->buildWindow(kickoffHour: '2030-04-25 18:00:00'),
            force: false,
        );

        $this->assertTrue($updated);
        $this->assertCount(1, $this->weatherClient->calls);
        $row = $this->cache->find($coordinates, $kickoffUtc);
        $this->assertNotNull($row);
        $this->assertSame(18.0, $row->snapshot->hours[0]->temperatureC);
    }

    public function testHttpFailureLeavesCacheUntouchedAndReturnsFalse(): void
    {
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $kickoffUtc = new DateTimeImmutable('2030-04-25 18:00:00', new DateTimeZone('UTC'));
        $this->cache->save($coordinates, $kickoffUtc, $this->snapshot(temperature: 22.0));
        $this->weatherClient->shouldThrow = true;

        $updated = $this->updater->update(
            $coordinates,
            $this->buildWindow(kickoffHour: '2030-04-25 18:00:00'),
            force: true,
        );

        $this->assertFalse($updated);
        $this->assertCount(1, $this->weatherClient->calls);
        $row = $this->cache->find($coordinates, $kickoffUtc);
        $this->assertNotNull($row);
        $this->assertSame(22.0, $row->snapshot->hours[0]->temperatureC);
    }

    public function testHttpFailureOnColdCacheReturnsFalseAndLeavesCacheEmpty(): void
    {
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $this->weatherClient->shouldThrow = true;

        $updated = $this->updater->update(
            $coordinates,
            $this->buildWindow(kickoffHour: '2030-04-25 18:00:00'),
            force: false,
        );

        $this->assertFalse($updated);
        $this->assertNull($this->cache->find($coordinates, new DateTimeImmutable('2030-04-25 18:00:00', new DateTimeZone('UTC'))));
    }

    public function testLocalKickoffIsPersistedInUtc(): void
    {
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $localKickoff = new DateTimeImmutable('2030-04-25 18:00:00', new DateTimeZone('Europe/Madrid'));
        $window = new WeatherWindow(
            kickoffHour: $localKickoff,
            hours: [
                $localKickoff->modify('-1 hour'),
                $localKickoff,
                $localKickoff->modify('+1 hour'),
            ],
        );

        $updated = $this->updater->update($coordinates, $window, force: false);

        $this->assertTrue($updated);
        $row = $this->db->get('weather_cache', '*');
        $this->assertNotFalse($row);
        $this->assertSame('2030-04-25 16:00:00', $row['forecast_ts']);
    }

    public function testClientReceivesWindowStartAndEndHours(): void
    {
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $window = $this->buildWindow(kickoffHour: '2030-04-25 18:00:00');

        $this->updater->update($coordinates, $window, force: false);

        $this->assertCount(1, $this->weatherClient->calls);
        $call = $this->weatherClient->calls[0];
        $this->assertSame('2030-04-25 17:00:00', $call['startHour']->format('Y-m-d H:i:s'));
        $this->assertSame('2030-04-25 21:00:00', $call['endHour']->format('Y-m-d H:i:s'));
    }

    private function buildWindow(string $kickoffHour): WeatherWindow
    {
        $kickoff = new DateTimeImmutable($kickoffHour, new DateTimeZone('UTC'));

        return new WeatherWindow(
            kickoffHour: $kickoff,
            hours: [
                $kickoff->modify('-1 hour'),
                $kickoff,
                $kickoff->modify('+1 hour'),
                $kickoff->modify('+2 hours'),
                $kickoff->modify('+3 hours'),
            ],
        );
    }

    private function snapshot(float $temperature): WeatherSnapshot
    {
        return new WeatherSnapshot([
            new WeatherHour(
                hour: new DateTimeImmutable('2030-04-25 18:00:00', new DateTimeZone('UTC')),
                temperatureC: $temperature,
                weatherCode: 0,
                windMetersPerSecond: 3.0,
                windDirectionDegrees: 0,
            ),
        ]);
    }
}
