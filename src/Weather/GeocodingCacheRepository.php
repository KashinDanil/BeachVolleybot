<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

use BeachVolleybot\Database\AbstractRepository;

final readonly class GeocodingCacheRepository extends AbstractRepository
{
    protected function table(): string
    {
        return 'geocoding_cache';
    }

    protected function primaryKeyColumn(): string
    {
        return 'query';
    }

    public function upsert(string $query, ?float $latitude, ?float $longitude): void
    {
        $this->db->pdo->prepare(
            'INSERT INTO ' . $this->table() . ' (query, latitude, longitude)
             VALUES (:query, :latitude, :longitude)
             ON CONFLICT (query) DO UPDATE SET
                latitude = excluded.latitude,
                longitude = excluded.longitude,
                fetched_at = CURRENT_TIMESTAMP'
        )->execute([
            ':query' => $query,
            ':latitude' => $latitude,
            ':longitude' => $longitude,
        ]);
    }
}
