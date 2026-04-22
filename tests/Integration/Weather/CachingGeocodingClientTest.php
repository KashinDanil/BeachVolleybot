<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Weather;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Tests\Stub\FakeGeocodingClient;
use BeachVolleybot\Weather\CachingGeocodingClient;
use BeachVolleybot\Weather\GeocodingCacheManager;
use BeachVolleybot\Weather\LocationCoordinates;

final class CachingGeocodingClientTest extends DatabaseTestCase
{
    private FakeGeocodingClient $innerClient;

    private GeocodingCacheManager $cache;

    private CachingGeocodingClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = file_get_contents(__DIR__ . '/../../../migrations/003_create_weather_tables.sql');
        $this->db->pdo->exec($schema);

        Connection::set($this->db);

        $this->innerClient = new FakeGeocodingClient();
        $this->cache = new GeocodingCacheManager();
        $this->client = new CachingGeocodingClient($this->innerClient, $this->cache);
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    public function testCacheMissCallsInnerClientAndCachesResult(): void
    {
        $this->innerClient->responses = ['Bogatell' => new LocationCoordinates(41.397, 2.211)];

        $coordinates = $this->client->resolve('Bogatell');

        $this->assertSame(41.397, $coordinates->latitude);
        $this->assertSame(['Bogatell'], $this->innerClient->queries);
        $this->assertSame(41.397, $this->cache->find('Bogatell')->coordinates->latitude);
    }

    public function testCacheHitReturnsCachedCoordinatesAndDoesNotCallInnerClient(): void
    {
        $this->cache->save('Bogatell', new LocationCoordinates(41.397, 2.211));

        $coordinates = $this->client->resolve('Bogatell');

        $this->assertSame(41.397, $coordinates->latitude);
        $this->assertSame([], $this->innerClient->queries);
    }

    public function testCachedNullReturnsNullAndDoesNotCallInnerClient(): void
    {
        $this->cache->save('UnknownPlace', null);

        $coordinates = $this->client->resolve('UnknownPlace');

        $this->assertNull($coordinates);
        $this->assertSame([], $this->innerClient->queries);
    }

    public function testInnerClientReturningNullIsCachedAsNegativeResult(): void
    {
        $coordinates = $this->client->resolve('UnknownPlace');

        $this->assertNull($coordinates);
        $this->assertSame(['UnknownPlace'], $this->innerClient->queries);

        $row = $this->cache->find('UnknownPlace');
        $this->assertNotNull($row);
        $this->assertNull($row->coordinates);
    }
}