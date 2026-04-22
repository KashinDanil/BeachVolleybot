<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Forecast\Client;

use BeachVolleybot\Weather\Forecast\Models\WeatherSnapshot;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use DateTimeImmutable;

interface WeatherApiClientInterface
{
    public function fetch(
        LocationCoordinates $coordinates,
        DateTimeImmutable $startHour,
        DateTimeImmutable $endHour,
    ): WeatherSnapshot;
}
