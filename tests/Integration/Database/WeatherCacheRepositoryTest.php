<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Weather\WeatherCacheRepository;

final class WeatherCacheRepositoryTest extends DatabaseTestCase
{
    private WeatherCacheRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = file_get_contents(__DIR__ . '/../../../migrations/003_create_weather_tables.sql');
        $this->db->pdo->exec($schema);

        $this->repository = new WeatherCacheRepository($this->db);
    }

    public function testFindByCoordsAndKickoffReturnsNullWhenNoMatch(): void
    {
        $result = $this->repository->findByCoordsAndKickoff(41.397, 2.211, '2026-04-15 16:00:00');

        $this->assertNull($result);
    }

    public function testUpsertInsertsNewRow(): void
    {
        $this->repository->upsert(41.397, 2.211, '2026-04-15 16:00:00', '[{"hour":"2026-04-15T18:00:00+02:00"}]');

        $row = $this->repository->findByCoordsAndKickoff(41.397, 2.211, '2026-04-15 16:00:00');
        $this->assertNotNull($row);
        $this->assertSame(41.397, (float) $row['latitude']);
        $this->assertSame(2.211, (float) $row['longitude']);
        $this->assertSame('2026-04-15 16:00:00', $row['forecast_ts']);
        $this->assertSame('[{"hour":"2026-04-15T18:00:00+02:00"}]', $row['data_json']);
        $this->assertNotEmpty($row['fetched_at']);
    }

    public function testReupsertReplacesDataJsonAndBumpsFetchedAt(): void
    {
        $this->repository->upsert(41.397, 2.211, '2026-04-15 16:00:00', '{"temp":22}');
        $this->db->pdo->exec(
            "UPDATE weather_cache SET fetched_at = '2020-01-01 00:00:00' WHERE forecast_ts = '2026-04-15 16:00:00'",
        );

        $this->repository->upsert(41.397, 2.211, '2026-04-15 16:00:00', '{"temp":25}');

        $row = $this->repository->findByCoordsAndKickoff(41.397, 2.211, '2026-04-15 16:00:00');
        $this->assertNotNull($row);
        $this->assertSame('{"temp":25}', $row['data_json']);
        $this->assertGreaterThan('2020-01-02 00:00:00', $row['fetched_at']);
        $this->assertSame(1, $this->db->count('weather_cache'));
    }

    public function testTwoUpsertsAtSameCoordsAndKickoffShareOneRow(): void
    {
        $this->repository->upsert(41.397, 2.211, '2026-04-15 16:00:00', '{}');
        $this->repository->upsert(41.397, 2.211, '2026-04-15 16:00:00', '{}');

        $this->assertSame(1, $this->db->count('weather_cache'));
    }

    public function testDifferentKickoffAtSameCoordsCreatesSecondRow(): void
    {
        $this->repository->upsert(41.397, 2.211, '2026-04-15 16:00:00', '{}');
        $this->repository->upsert(41.397, 2.211, '2026-04-15 08:00:00', '{}');

        $this->assertSame(2, $this->db->count('weather_cache'));
    }

    public function testDifferentCoordsCreatesSecondRow(): void
    {
        $this->repository->upsert(41.397, 2.211, '2026-04-15 16:00:00', '{}');
        $this->repository->upsert(41.400, 2.220, '2026-04-15 16:00:00', '{}');

        $this->assertSame(2, $this->db->count('weather_cache'));
    }

    public function testFoundRowIncludesFetchedAtForCallerFreshnessChecks(): void
    {
        $this->repository->upsert(41.397, 2.211, '2026-04-15 16:00:00', '{}');

        $row = $this->repository->findByCoordsAndKickoff(41.397, 2.211, '2026-04-15 16:00:00');
        $this->assertNotNull($row);
        $this->assertArrayHasKey('fetched_at', $row);
        $this->assertNotEmpty($row['fetched_at']);
    }
}
