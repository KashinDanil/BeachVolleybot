<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

use BeachVolleybot\Database\Connection;
use DateTimeImmutable;
use DateTimeZone;

final readonly class LocationCacheManager
{
    private LocationCacheRepository $repository;

    public function __construct()
    {
        $this->repository = new LocationCacheRepository(Connection::get());
    }

    public function find(string $query): ?CachedLocationRow
    {
        $row = $this->repository->findById($query);

        if (null === $row) {
            return null;
        }

        return new CachedLocationRow(
            coordinates: $this->coordinatesFromRow($row),
            fetchedAt: new DateTimeImmutable((string)$row['fetched_at'], new DateTimeZone('UTC')),
        );
    }

    public function remember(string $query, ?LocationCoordinates $coordinates): void
    {
        $this->repository->upsert($query, $coordinates?->latitude, $coordinates?->longitude);
    }

    /** @param array<string, mixed> $row */
    private function coordinatesFromRow(array $row): ?LocationCoordinates
    {
        if (null === $row['latitude'] || null === $row['longitude']) {
            return null;
        }

        return new LocationCoordinates(
            latitude: (float)$row['latitude'],
            longitude: (float)$row['longitude'],
        );
    }
}
