<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Forecast;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Weather\Queue\WeatherQueuePayload;
use Throwable;

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
        $gameRows = new GameRepository(Connection::get())->findByKickoffBetween($range->from, $range->until);
        $gameRecords = [];

        foreach ($gameRows as $gameRow) {
            try {
                $game = GameRecord::fromRow($gameRow);

                if ($forecast->id() === WeatherQueuePayload::forGameRecord($game)->id()) {
                    $gameRecords[] = $game;
                }
            } catch (Throwable $e) {
                Logger::logApp('Forecast games lookup skipped game id=' . (int)$gameRow['game_id'] . ': ' . $e->getMessage());
            }
        }

        return $gameRecords;
    }
}
