<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\Stub;

use BeachVolleybot\Weather\LocationCoordinates;
use BeachVolleybot\Weather\WeatherApiClientInterface;
use BeachVolleybot\Weather\WeatherHour;
use BeachVolleybot\Weather\WeatherSnapshot;
use DateTimeImmutable;
use RuntimeException;

final class FakeWeatherApiClient implements WeatherApiClientInterface
{
    /** @var list<array{coords: LocationCoordinates, startHour: DateTimeImmutable, endHour: DateTimeImmutable}> */
    public array $calls = [];

    public ?WeatherSnapshot $nextSnapshot = null;

    public bool $shouldThrow = false;

    public function fetch(
        LocationCoordinates $coordinates,
        DateTimeImmutable $startHour,
        DateTimeImmutable $endHour,
    ): WeatherSnapshot {
        $this->calls[] = [
            'coords' => $coordinates,
            'startHour' => $startHour,
            'endHour' => $endHour,
        ];

        if ($this->shouldThrow) {
            throw new RuntimeException('Open-Meteo request failed: test failure');
        }

        return $this->nextSnapshot ?? $this->defaultSnapshot($startHour);
    }

    private function defaultSnapshot(DateTimeImmutable $startHour): WeatherSnapshot
    {
        return new WeatherSnapshot([
            new WeatherHour(
                hour: $startHour,
                temperatureC: 22.0,
                weatherCode: 0,
                windMetersPerSecond: 3.0,
                windDirectionDegrees: 0,
            ),
        ]);
    }
}
