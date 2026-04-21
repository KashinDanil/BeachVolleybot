<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

use Medoo\Medoo;

final readonly class WeatherCacheRepository
{
    private const string TABLE = 'weather_cache';

    public function __construct(
        private Medoo $db,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function findByCoordsAndKickoff(float $latitude, float $longitude, string $forecastTs): ?array
    {
        return $this->db->get(self::TABLE, '*', $this->primaryKey($latitude, $longitude, $forecastTs)) ?: null;
    }

    public function upsert(float $latitude, float $longitude, string $forecastTs, string $dataJson): void
    {
        $this->db->pdo->prepare(
            'INSERT INTO ' . self::TABLE . ' (latitude, longitude, forecast_ts, data_json)
             VALUES (:latitude, :longitude, :forecast_ts, :data_json)
             ON CONFLICT (latitude, longitude, forecast_ts) DO UPDATE SET
                data_json = excluded.data_json,
                fetched_at = CURRENT_TIMESTAMP'
        )->execute([
            ':latitude' => $latitude,
            ':longitude' => $longitude,
            ':forecast_ts' => $forecastTs,
            ':data_json' => $dataJson,
        ]);
    }

    /** @return array<string, float|string> */
    private function primaryKey(float $latitude, float $longitude, string $forecastTs): array
    {
        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'forecast_ts' => $forecastTs,
        ];
    }
}