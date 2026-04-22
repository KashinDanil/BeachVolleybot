<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

use DateTimeImmutable;

final readonly class GameWeatherLookupResult
{
    public function __construct(
        public WeatherCacheRow $row,
        public DateTimeImmutable $kickoffHour,
    ) {
    }
}