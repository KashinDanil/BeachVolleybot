<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Forecast;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Weather\Forecast\Models\WeatherWindow;
use DateTimeImmutable;

final readonly class WeatherWindowResolver
{
    public const int HOURS_BEFORE_KICKOFF  = 1;
    public const int HOURS_AFTER_KICKOFF   = 3;
    public const int FORECAST_HORIZON_DAYS = 7;

    public function windowForGame(GameInterface $game): WeatherWindow
    {
        $kickoffHour = $this->resolveKickoffHour($game);

        if (null === $kickoffHour) {
            return new WeatherWindow($game->getCreatedAt(), []);
        }

        if (!$this->isWithinForecastHorizon($kickoffHour)) {
            return new WeatherWindow($kickoffHour, []);
        }

        return new WeatherWindow($kickoffHour, $this->buildHourRangeAround($kickoffHour));
    }

    private function resolveKickoffHour(GameInterface $game): ?DateTimeImmutable
    {
        $kickoff = GameDateTimeResolver::resolve($game->getTitle(), $game->getCreatedAt());
        if (null === $kickoff) {
            return null;
        }

        return $this->truncateToHour($kickoff);
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