<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Forecast\Cache;

use BeachVolleybot\Weather\Forecast\Models\WeatherSnapshot;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use DateTimeImmutable;

final readonly class WeatherCacheRow
{
    public function __construct(
        public LocationCoordinates $coordinates,
        public DateTimeImmutable $fetchedAt,
        public WeatherSnapshot $snapshot,
    ) {
    }
}
