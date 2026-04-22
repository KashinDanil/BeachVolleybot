<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Weather\Forecast\Cache\WeatherCacheManager;
use BeachVolleybot\Weather\Forecast\Models\WeatherHour;
use BeachVolleybot\Weather\Forecast\Models\WeatherSnapshot;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use DateTimeImmutable;
use DateTimeZone;

final class WeatherCacheManagerTest extends DatabaseTestCase
{
    private WeatherCacheManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = file_get_contents(__DIR__ . '/../../../migrations/003_create_weather_tables.sql');
        $this->db->pdo->exec($schema);

        Connection::set($this->db);
        $this->manager = new WeatherCacheManager();
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    public function testFindReturnsNullWhenNoRow(): void
    {
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $kickoffHour = new DateTimeImmutable('2026-04-15 16:00:00', new DateTimeZone('UTC'));

        $this->assertNull($this->manager->find($coordinates, $kickoffHour));
    }

    public function testSaveAndFindRoundTripCoordinatesAndSnapshot(): void
    {
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $kickoffHour = new DateTimeImmutable('2026-04-15 16:00:00', new DateTimeZone('UTC'));
        $snapshot = $this->makeSnapshot();

        $this->manager->save($coordinates, $kickoffHour, $snapshot);

        $row = $this->manager->find($coordinates, $kickoffHour);
        $this->assertNotNull($row);
        $this->assertSame(41.397, $row->coordinates->latitude);
        $this->assertSame(2.211, $row->coordinates->longitude);
        $this->assertCount(2, $row->snapshot->hours);
        $this->assertSame(22.5, $row->snapshot->hours[0]->temperatureC);
    }

    public function testKickoffConvertedToUtcBeforePersisting(): void
    {
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $kickoffLocal = new DateTimeImmutable('2026-04-15 18:00:00', new DateTimeZone('+02:00'));
        $kickoffUtc = $kickoffLocal->setTimezone(new DateTimeZone('UTC'));

        $this->manager->save($coordinates, $kickoffLocal, $this->makeSnapshot());

        // Should be findable using either the local or the UTC-equivalent kickoff hour.
        $this->assertNotNull($this->manager->find($coordinates, $kickoffLocal));
        $this->assertNotNull($this->manager->find($coordinates, $kickoffUtc));
    }

    public function testHourOffsetPreservedThroughRoundTrip(): void
    {
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $kickoffHour = new DateTimeImmutable('2026-04-15 16:00:00', new DateTimeZone('UTC'));
        $hourWithOffset = new DateTimeImmutable('2026-04-15 17:00:00+02:00');

        $this->manager->save($coordinates, $kickoffHour, new WeatherSnapshot([
            new WeatherHour($hourWithOffset, 22.5, 0, 3.2, 180),
        ]));

        $row = $this->manager->find($coordinates, $kickoffHour);
        $this->assertNotNull($row);
        $this->assertSame($hourWithOffset->format(DATE_ATOM), $row->snapshot->hours[0]->hour->format(DATE_ATOM));
    }

    public function testFetchedAtHydratedAsDateTimeImmutableInUtc(): void
    {
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $kickoffHour = new DateTimeImmutable('2026-04-15 16:00:00', new DateTimeZone('UTC'));

        $this->manager->save($coordinates, $kickoffHour, $this->makeSnapshot());

        $row = $this->manager->find($coordinates, $kickoffHour);
        $this->assertNotNull($row);
        $this->assertSame('UTC', $row->fetchedAt->getTimezone()->getName());
    }

    public function testReSaveOverwritesDataAndBumpsFetchedAt(): void
    {
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $kickoffHour = new DateTimeImmutable('2026-04-15 16:00:00', new DateTimeZone('UTC'));

        $this->manager->save($coordinates, $kickoffHour, $this->makeSnapshot(temperature: 22.0));
        $this->db->pdo->exec(
            "UPDATE weather_cache SET fetched_at = '2020-01-01 00:00:00' WHERE forecast_ts = '2026-04-15 16:00:00'",
        );
        $this->manager->save($coordinates, $kickoffHour, $this->makeSnapshot(temperature: 25.0));

        $row = $this->manager->find($coordinates, $kickoffHour);
        $this->assertNotNull($row);
        $this->assertSame(25.0, $row->snapshot->hours[0]->temperatureC);
        $this->assertGreaterThan(new DateTimeImmutable('2020-01-02', new DateTimeZone('UTC')), $row->fetchedAt);
        $this->assertSame(1, $this->db->count('weather_cache'));
    }

    private function makeSnapshot(float $temperature = 22.5): WeatherSnapshot
    {
        return new WeatherSnapshot([
            new WeatherHour(
                hour: new DateTimeImmutable('2026-04-15 15:00:00+00:00'),
                temperatureC: $temperature,
                weatherCode: 0,
                windMetersPerSecond: 3.2,
                windDirectionDegrees: 180,
            ),
            new WeatherHour(
                hour: new DateTimeImmutable('2026-04-15 16:00:00+00:00'),
                temperatureC: $temperature + 0.5,
                weatherCode: 1,
                windMetersPerSecond: 3.1,
                windDirectionDegrees: 175,
            ),
        ]);
    }
}
