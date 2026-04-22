<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Forecast\Models;

use DateTimeImmutable;
use JsonSerializable;

final readonly class WeatherHour implements JsonSerializable
{
    public function __construct(
        public DateTimeImmutable $hour,
        public float $temperatureC,
        public int $weatherCode,
        public float $windMetersPerSecond,
        public int $windDirectionDegrees,
    ) {
    }

    /**
     * @return array{
     *     hour: string,
     *     temperatureC: float,
     *     weatherCode: int,
     *     windMetersPerSecond: float,
     *     windDirectionDegrees: int,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'hour' => $this->hour->format(DATE_ATOM),
            'temperatureC' => $this->temperatureC,
            'weatherCode' => $this->weatherCode,
            'windMetersPerSecond' => $this->windMetersPerSecond,
            'windDirectionDegrees' => $this->windDirectionDegrees,
        ];
    }

    /**
     * @param array{
     *     hour: string,
     *     temperatureC: float|int,
     *     weatherCode: int,
     *     windMetersPerSecond: float|int,
     *     windDirectionDegrees: int,
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            new DateTimeImmutable($data['hour']),
            (float) $data['temperatureC'],
            (int) $data['weatherCode'],
            (float) $data['windMetersPerSecond'],
            (int) $data['windDirectionDegrees'],
        );
    }
}
