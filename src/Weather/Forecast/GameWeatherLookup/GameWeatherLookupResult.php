<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Forecast\GameWeatherLookup;

use BeachVolleybot\Weather\Forecast\Cache\WeatherCacheRow;
use DateTimeImmutable;

final readonly class GameWeatherLookupResult
{
    public function __construct(
        public WeatherCacheRow $row,
        public DateTimeImmutable $kickoffHour,
    ) {
    }
}