<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

use DateTimeImmutable;

interface WeatherApiClientInterface
{
    public function fetch(
        LocationCoordinates $coordinates,
        DateTimeImmutable $startHour,
        DateTimeImmutable $endHour,
    ): WeatherSnapshot;
}
