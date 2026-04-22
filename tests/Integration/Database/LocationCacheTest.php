<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Weather\Location\Cache\LocationCacheManager;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;

final class LocationCacheTest extends DatabaseTestCase
{
    private LocationCacheManager $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = file_get_contents(__DIR__ . '/../../../migrations/003_create_weather_tables.sql');
        $this->db->pdo->exec($schema);

        Connection::set($this->db);
        $this->cache = new LocationCacheManager();
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    public function testFindReturnsNullWhenNoRow(): void
    {
        $this->assertNull($this->cache->find('Bogatell'));
    }

    public function testRememberAndFindRoundTripHit(): void
    {
        $this->cache->remember('Bogatell', new LocationCoordinates(41.397, 2.211));

        $row = $this->cache->find('Bogatell');
        $this->assertNotNull($row);
        $this->assertNotNull($row->coordinates);
        $this->assertSame(41.397, $row->coordinates->latitude);
        $this->assertSame(2.211, $row->coordinates->longitude);
    }

    public function testRememberMissIsHydratedAsRowWithNullCoordinates(): void
    {
        $this->cache->remember('nonexistent-place', null);

        $row = $this->cache->find('nonexistent-place');
        $this->assertNotNull($row);
        $this->assertNull($row->coordinates);
    }

    public function testFetchedAtHydratedAsDateTimeImmutableInUtc(): void
    {
        $this->cache->remember('Bogatell', new LocationCoordinates(41.397, 2.211));

        $row = $this->cache->find('Bogatell');
        $this->assertNotNull($row);
        $this->assertSame('UTC', $row->fetchedAt->getTimezone()->getName());
    }

    public function testMissCanBeOverwrittenByHit(): void
    {
        $this->cache->remember('Bogatell', null);
        $this->cache->remember('Bogatell', new LocationCoordinates(41.397, 2.211));

        $row = $this->cache->find('Bogatell');
        $this->assertNotNull($row);
        $this->assertNotNull($row->coordinates);
        $this->assertSame(41.397, $row->coordinates->latitude);
    }
}
