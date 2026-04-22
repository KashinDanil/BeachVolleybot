<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Forecast\Models;

use DateTimeImmutable;

final readonly class WeatherWindow
{
    /** @param list<DateTimeImmutable> $hours */
    public function __construct(
        public DateTimeImmutable $kickoffHour,
        public array $hours,
    ) {
    }
}
