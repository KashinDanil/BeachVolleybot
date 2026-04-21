<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

use DateTimeImmutable;
use DateTimeZone;

final readonly class OpenMeteoWeatherClient extends AbstractOpenMeteoClient implements WeatherApiClientInterface
{
    public const string BASE_URL = 'https://api.open-meteo.com/v1/forecast';

    private const string HOURLY_METRICS     = 'temperature_2m,weather_code,wind_speed_10m,wind_direction_10m';
    private const float  KMH_TO_MS_DIVISOR  = 3.6;
    private const int    MS_ROUND_PRECISION = 1;

    public function fetch(
        LocationCoordinates $coordinates,
        DateTimeImmutable $startHour,
        DateTimeImmutable $endHour,
    ): WeatherSnapshot {
        $response = $this->get(self::BASE_URL, [
            'latitude' => $coordinates->latitude,
            'longitude' => $coordinates->longitude,
            'hourly' => self::HOURLY_METRICS,
            'timezone' => 'auto',
            'start_hour' => $startHour->format('Y-m-d\TH:i'),
            'end_hour' => $endHour->format('Y-m-d\TH:i'),
        ]);

        return $this->parseResponse($response);
    }

    /** @param array<string, mixed> $response */
    private function parseResponse(array $response): WeatherSnapshot
    {
        $timezone = new DateTimeZone((string)($response['timezone'] ?? 'UTC'));
        $hourly = (array)($response['hourly'] ?? []);
        $times = (array)($hourly['time'] ?? []);
        $temperatures = (array)($hourly['temperature_2m'] ?? []);
        $codes = (array)($hourly['weather_code'] ?? []);
        $windSpeedsKmh = (array)($hourly['wind_speed_10m'] ?? []);
        $windDirections = (array)($hourly['wind_direction_10m'] ?? []);

        $hours = [];
        foreach ($times as $index => $time) {
            $hours[] = new WeatherHour(
                hour: new DateTimeImmutable((string)$time, $timezone),
                temperatureC: (float)$temperatures[$index],
                weatherCode: (int)$codes[$index],
                windMetersPerSecond: $this->toMetersPerSecond((float)$windSpeedsKmh[$index]),
                windDirectionDegrees: (int)$windDirections[$index],
            );
        }

        return new WeatherSnapshot($hours);
    }

    private function toMetersPerSecond(float $kmh): float
    {
        return round($kmh / self::KMH_TO_MS_DIVISOR, self::MS_ROUND_PRECISION);
    }
}
