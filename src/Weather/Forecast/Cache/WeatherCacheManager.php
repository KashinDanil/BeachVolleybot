<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Forecast\Cache;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\Timestamp;
use BeachVolleybot\Weather\Forecast\Models\WeatherSnapshot;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use DateTimeImmutable;

final readonly class WeatherCacheManager
{
    private WeatherCacheRepository $repository;

    public function __construct()
    {
        $this->repository = new WeatherCacheRepository(Connection::get());
    }

    public function find(LocationCoordinates $coordinates, DateTimeImmutable $kickoffHour): ?WeatherCacheRow
    {
        $row = $this->repository->findByCoordsAndKickoff(
            $coordinates->latitude,
            $coordinates->longitude,
            Timestamp::format($kickoffHour),
        );

        return null === $row ? null : $this->hydrate($row);
    }

    public function save(
        LocationCoordinates $coordinates,
        DateTimeImmutable $kickoffHour,
        WeatherSnapshot $snapshot,
    ): void {
        $this->repository->upsert(
            $coordinates->latitude,
            $coordinates->longitude,
            Timestamp::format($kickoffHour),
            json_encode($snapshot, JSON_THROW_ON_ERROR),
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): WeatherCacheRow
    {
        return new WeatherCacheRow(
            coordinates: new LocationCoordinates(
                latitude: (float)$row['latitude'],
                longitude: (float)$row['longitude'],
            ),
            fetchedAt: Timestamp::parse((string)$row['fetched_at']),
            snapshot: WeatherSnapshot::fromArray(
                json_decode((string)$row['data_json'], associative: true, flags: JSON_THROW_ON_ERROR),
            ),
        );
    }
}
