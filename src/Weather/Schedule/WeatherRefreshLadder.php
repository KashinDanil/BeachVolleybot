<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Schedule;

use DateTimeImmutable;

/**
 * How stale a game's forecast may get before the scheduler asks for a new one. An hour is the
 * floor because Open-Meteo's fastest model run lands hourly — a tighter rung would fetch the
 * same numbers again.
 */
final readonly class WeatherRefreshLadder
{
    private const int HOUR = 3600;

    /** Hours until kickoff => hours the forecast may age before a re-fetch, nearest kickoff first. */
    private const array MAX_FORECAST_AGE_BY_HOURS_TO_KICKOFF = [
        24 => 1,
        48 => 3,
        72 => 6,
    ];

    private const int DEFAULT_MAX_FORECAST_AGE_HOURS = 12;

    public function isDue(DateTimeImmutable $now, DateTimeImmutable $kickoffAt, ?DateTimeImmutable $fetchedAt): bool
    {
        if (null === $fetchedAt) {
            return true;
        }

        return $now->getTimestamp() - $fetchedAt->getTimestamp() >= $this->maxCacheAgeSeconds($now, $kickoffAt);
    }

    private function maxCacheAgeSeconds(DateTimeImmutable $now, DateTimeImmutable $kickoffAt): int
    {
        $hoursUntilKickoff = ($kickoffAt->getTimestamp() - $now->getTimestamp()) / self::HOUR;

        foreach (self::MAX_FORECAST_AGE_BY_HOURS_TO_KICKOFF as $withinHours => $maxAgeHours) {
            if ($hoursUntilKickoff <= $withinHours) {
                return $maxAgeHours * self::HOUR;
            }
        }

        return self::DEFAULT_MAX_FORECAST_AGE_HOURS * self::HOUR;
    }
}
