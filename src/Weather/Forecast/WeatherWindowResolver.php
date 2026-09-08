<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Forecast;

use BeachVolleybot\Weather\Forecast\Models\WeatherWindow;
use DateTimeImmutable;

final readonly class WeatherWindowResolver
{
    public const int HOURS_BEFORE_KICKOFF  = 1;
    public const int HOURS_AFTER_KICKOFF   = 3;
    public const int FORECAST_HORIZON_DAYS = 7;

    private const int SECONDS_PER_HOUR = 3600;

    public function windowFor(DateTimeImmutable $kickoffAt, DateTimeImmutable $now = new DateTimeImmutable()): WeatherWindow
    {
        $kickoffHour = $this->roundToNearestHour($kickoffAt);

        if (!$this->isWithinForecastHorizon($kickoffHour, $now)) {
            return new WeatherWindow($kickoffHour, []);
        }

        return new WeatherWindow($kickoffHour, $this->buildHourRangeAround($kickoffHour));
    }

    private function isWithinForecastHorizon(DateTimeImmutable $kickoffHour, DateTimeImmutable $now): bool
    {
        $horizonCutoff = $now->modify('+' . self::FORECAST_HORIZON_DAYS . ' days');

        return $kickoffHour >= $this->truncateToHour($now)
            && $kickoffHour <= $horizonCutoff;
    }

    private function truncateToHour(DateTimeImmutable $dateTime): DateTimeImmutable
    {
        return $dateTime->setTimestamp(intdiv($dateTime->getTimestamp(), self::SECONDS_PER_HOUR) * self::SECONDS_PER_HOUR);
    }

    private function roundToNearestHour(DateTimeImmutable $dateTime): DateTimeImmutable
    {
        return $dateTime->setTimestamp((int)round($dateTime->getTimestamp() / self::SECONDS_PER_HOUR) * self::SECONDS_PER_HOUR);
    }

    /** @return list<DateTimeImmutable> */
    private function buildHourRangeAround(DateTimeImmutable $kickoffHour): array
    {
        $hours = [];

        // Real hours, not wall clock: modify('+1 hours') repeats or skips an hour across a DST switch.
        for ($offset = -self::HOURS_BEFORE_KICKOFF; $offset <= self::HOURS_AFTER_KICKOFF; $offset++) {
            $hours[] = $kickoffHour->setTimestamp($kickoffHour->getTimestamp() + $offset * self::SECONDS_PER_HOUR);
        }

        return $hours;
    }
}