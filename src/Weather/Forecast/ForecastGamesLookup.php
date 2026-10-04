<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Forecast;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Weather\Queue\WeatherQueuePayload;

/** The games that read a given forecast — the inverse of GameWeatherLookup. */
final readonly class ForecastGamesLookup
{
    public function __construct(
        private WeatherWindowResolver $windowResolver = new WeatherWindowResolver(),
    ) {
    }

    /**
     * Candidates come from the kickoff range that rounds onto the forecast, then each is put
     * back through the same identity the forecast was built from, so the two cannot disagree.
     *
     * @return list<GameRecord>
     */
    public function findGameRecords(WeatherQueuePayload $forecast): array
    {
        $range = $this->windowResolver->rangeRoundingTo($forecast->forecastTs);
        $candidates = new GameManager()->findGameRecordsByKickoffBetween($range->from, $range->until);
        $gameRecords = [];

        foreach ($candidates as $game) {
            if ($forecast->id() === WeatherQueuePayload::forGameRecord($game)->id()) {
                $gameRecords[] = $game;
            }
        }

        return $gameRecords;
    }
}
