<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Schedule;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Weather\Forecast\Cache\WeatherCacheManager;
use BeachVolleybot\Weather\Forecast\WeatherWindowResolver;
use BeachVolleybot\Weather\Queue\WeatherEnqueuer;
use BeachVolleybot\Weather\Queue\WeatherQueuePayload;
use DateTimeImmutable;
use Throwable;

/** Queues one refresh per forecast the ladder calls due, however many games share it. */
final readonly class WeatherRefreshScheduler
{
    public function __construct(
        private WeatherEnqueuer $enqueuer = new WeatherEnqueuer(),
        private WeatherRefreshLadder $ladder = new WeatherRefreshLadder(),
        private WeatherCacheManager $weatherCache = new WeatherCacheManager(),
    ) {
    }

    public function scan(): void
    {
        $now = new DateTimeImmutable();
        $seenForecasts = [];

        foreach ($this->upcomingGames($now) as $game) {
            $forecast = WeatherQueuePayload::forGameRecord($game);

            // Games arrive soonest first, so the first on a forecast holds its strictest rung.
            if (isset($seenForecasts[$forecast->id()])) {
                continue;
            }

            $seenForecasts[$forecast->id()] = true;
            $this->enqueueIfDue($forecast, $game->kickoffAt, $now);
        }
    }

    /**
     * @return iterable<GameRecord>
     */
    private function upcomingGames(DateTimeImmutable $now): iterable
    {
        $horizon = $now->modify('+' . WeatherWindowResolver::FORECAST_HORIZON_DAYS . ' days');

        foreach (new GameRepository(Connection::get())->findUpcoming($now, $horizon) as $gameRow) {
            try {
                yield GameRecord::fromRow($gameRow);
            } catch (Throwable $e) {
                Logger::logApp('Weather refresh scan skipped game id=' . (int)$gameRow['game_id'] . ': ' . $e->getMessage());
            }
        }
    }

    private function enqueueIfDue(WeatherQueuePayload $forecast, DateTimeImmutable $kickoffAt, DateTimeImmutable $now): void
    {
        try {
            $fetchedAt = $this->weatherCache->find($forecast->coordinates, $forecast->forecastTs)?->fetchedAt;

            if ($this->ladder->isDue($now, $kickoffAt, $fetchedAt)) {
                $this->enqueuer->enqueue($forecast);
            }
        } catch (Throwable $e) {
            Logger::logApp('Weather refresh scan skipped forecast ' . $forecast->id() . ': ' . $e->getMessage());
        }
    }
}
