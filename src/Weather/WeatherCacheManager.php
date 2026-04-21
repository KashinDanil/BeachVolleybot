<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

use BeachVolleybot\Database\Connection;
use DateTimeImmutable;
use DateTimeZone;

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
            $this->formatTimestamp($kickoffHour),
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
            $this->formatTimestamp($kickoffHour),
            json_encode($snapshot, JSON_THROW_ON_ERROR),
        );
    }

    private function formatTimestamp(DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): WeatherCacheRow
    {
        return new WeatherCacheRow(
            coordinates: new LocationCoordinates(
                latitude: (float)$row['latitude'],
                longitude: (float)$row['longitude'],
            ),
            fetchedAt: new DateTimeImmutable((string)$row['fetched_at'], new DateTimeZone('UTC')),
            snapshot: WeatherSnapshot::fromArray(
                json_decode((string)$row['data_json'], associative: true, flags: JSON_THROW_ON_ERROR),
            ),
        );
    }
}
