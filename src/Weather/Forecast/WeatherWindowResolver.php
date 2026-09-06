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

    public function windowFor(DateTimeImmutable $kickoffAt): WeatherWindow
    {
        $kickoffHour = $this->roundToNearestHour($kickoffAt);

        if (!$this->isWithinForecastHorizon($kickoffHour)) {
            return new WeatherWindow($kickoffHour, []);
        }

        return new WeatherWindow($kickoffHour, $this->buildHourRangeAround($kickoffHour));
    }

    private function isWithinForecastHorizon(DateTimeImmutable $kickoffHour): bool
    {
        $now = new DateTimeImmutable();
        $horizonCutoff = $now->modify('+' . self::FORECAST_HORIZON_DAYS . ' days');

        return $kickoffHour >= $this->truncateToHour($now)
            && $kickoffHour <= $horizonCutoff;
    }

    private function truncateToHour(DateTimeImmutable $dateTime): DateTimeImmutable
    {
        return $dateTime->setTime((int)$dateTime->format('G'), 0);
    }

    private function roundToNearestHour(DateTimeImmutable $dateTime): DateTimeImmutable
    {
        $minutes = (int)$dateTime->format('i');
        $base = 30 <= $minutes ? $dateTime->modify('+1 hour') : $dateTime;

        return $this->truncateToHour($base);
    }

    /** @return list<DateTimeImmutable> */
    private function buildHourRangeAround(DateTimeImmutable $kickoffHour): array
    {
        $hours = [];

        for ($offset = -self::HOURS_BEFORE_KICKOFF; $offset <= self::HOURS_AFTER_KICKOFF; $offset++) {
            $hours[] = $kickoffHour->modify("$offset hours");
        }

        return $hours;
    }
}