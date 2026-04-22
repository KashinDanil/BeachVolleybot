<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Weather\Location\Cache\LocationCacheRepository;

final class LocationCacheRepositoryTest extends DatabaseTestCase
{
    private LocationCacheRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = file_get_contents(__DIR__ . '/../../../migrations/003_create_weather_tables.sql');
        $this->db->pdo->exec($schema);

        $this->repository = new LocationCacheRepository($this->db);
    }

    public function testFindByIdReturnsNullWhenNoRow(): void
    {
        $this->assertNull($this->repository->findById('Bogatell'));
    }

    public function testUpsertStoresHit(): void
    {
        $this->repository->upsert('Bogatell', 41.397, 2.211);

        $row = $this->repository->findById('Bogatell');
        $this->assertNotNull($row);
        $this->assertSame(41.397, (float) $row['latitude']);
        $this->assertSame(2.211, (float) $row['longitude']);
    }

    public function testUpsertStoresMissAsRowWithNullCoordinates(): void
    {
        $this->repository->upsert('nonexistent-place', null, null);

        $row = $this->repository->findById('nonexistent-place');
        $this->assertNotNull($row);
        $this->assertNull($row['latitude']);
        $this->assertNull($row['longitude']);
    }

    public function testReupsertReplacesCoordinates(): void
    {
        $this->repository->upsert('Bogatell', 0.0, 0.0);
        $this->repository->upsert('Bogatell', 41.397, 2.211);

        $row = $this->repository->findById('Bogatell');
        $this->assertNotNull($row);
        $this->assertSame(41.397, (float) $row['latitude']);
        $this->assertSame(1, $this->db->count('location_cache'));
    }

    public function testLookupIsByteExactAndCaseSensitive(): void
    {
        $this->repository->upsert('Bogatell', 41.397, 2.211);

        $this->assertNull($this->repository->findById('bogatell'));
        $this->assertNull($this->repository->findById('Bogatell '));
        $this->assertNotNull($this->repository->findById('Bogatell'));
    }

    public function testMissCanBeOverwrittenByHit(): void
    {
        $this->repository->upsert('Bogatell', null, null);
        $this->repository->upsert('Bogatell', 41.397, 2.211);

        $row = $this->repository->findById('Bogatell');
        $this->assertNotNull($row);
        $this->assertSame(41.397, (float) $row['latitude']);
    }

    public function testFoundRowIncludesFetchedAt(): void
    {
        $this->repository->upsert('Bogatell', 41.397, 2.211);

        $row = $this->repository->findById('Bogatell');
        $this->assertNotNull($row);
        $this->assertArrayHasKey('fetched_at', $row);
        $this->assertNotEmpty($row['fetched_at']);
    }
}
