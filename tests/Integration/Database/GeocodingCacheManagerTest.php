<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Weather\GeocodingCacheManager;
use BeachVolleybot\Weather\LocationCoordinates;

final class GeocodingCacheManagerTest extends DatabaseTestCase
{
    private GeocodingCacheManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = file_get_contents(__DIR__ . '/../../../migrations/003_create_weather_tables.sql');
        $this->db->pdo->exec($schema);

        Connection::set($this->db);
        $this->manager = new GeocodingCacheManager();
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    public function testFindReturnsNullWhenNoRow(): void
    {
        $this->assertNull($this->manager->find('Bogatell'));
    }

    public function testSaveAndFindRoundTripHit(): void
    {
        $this->manager->save('Bogatell', new LocationCoordinates(41.397, 2.211));

        $row = $this->manager->find('Bogatell');
        $this->assertNotNull($row);
        $this->assertNotNull($row->coordinates);
        $this->assertSame(41.397, $row->coordinates->latitude);
        $this->assertSame(2.211, $row->coordinates->longitude);
    }

    public function testSaveMissIsHydratedAsRowWithNullCoordinates(): void
    {
        $this->manager->save('nonexistent-place', null);

        $row = $this->manager->find('nonexistent-place');
        $this->assertNotNull($row);
        $this->assertNull($row->coordinates);
    }

    public function testFetchedAtHydratedAsDateTimeImmutableInUtc(): void
    {
        $this->manager->save('Bogatell', new LocationCoordinates(41.397, 2.211));

        $row = $this->manager->find('Bogatell');
        $this->assertNotNull($row);
        $this->assertSame('UTC', $row->fetchedAt->getTimezone()->getName());
    }

    public function testMissCanBeOverwrittenByHit(): void
    {
        $this->manager->save('Bogatell', null);
        $this->manager->save('Bogatell', new LocationCoordinates(41.397, 2.211));

        $row = $this->manager->find('Bogatell');
        $this->assertNotNull($row);
        $this->assertNotNull($row->coordinates);
        $this->assertSame(41.397, $row->coordinates->latitude);
    }
}
