<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Schedule;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Weather\Forecast\GameWeatherLookup\GameWeatherLookup;
use BeachVolleybot\Weather\Forecast\WeatherWindowResolver;
use BeachVolleybot\Weather\Queue\WeatherEnqueuer;
use DateTimeImmutable;
use Throwable;

/** Queues a forecast refresh for every upcoming game whose cached forecast has aged past its rung. */
final readonly class WeatherRefreshScheduler
{
    public function __construct(
        private WeatherEnqueuer $enqueuer = new WeatherEnqueuer(),
        private WeatherRefreshLadder $ladder = new WeatherRefreshLadder(),
        private GameWeatherLookup $weatherLookup = new GameWeatherLookup(),
    ) {
    }

    public function scan(): void
    {
        $now = new DateTimeImmutable();
        $horizon = $now->modify('+' . WeatherWindowResolver::FORECAST_HORIZON_DAYS . ' days');
        $gameRows = new GameRepository(Connection::get())->findUpcoming($now, $horizon);

        foreach ($gameRows as $gameRow) {
            $this->enqueueIfDue($gameRow, $now);
        }
    }

    private function enqueueIfDue(array $gameRow, DateTimeImmutable $now): void
    {
        try {
            $gameRecord = GameRecord::fromRow($gameRow);
            $fetchedAt = $this->weatherLookup->findForGameRecord($gameRecord)?->row->fetchedAt;

            if ($this->ladder->isDue($now, $gameRecord->kickoffAt, $fetchedAt)) {
                $this->enqueuer->enqueue($gameRecord->gameId);
            }
        } catch (Throwable $e) {
            Logger::logApp('Weather refresh scan skipped game id=' . (int)$gameRow['game_id'] . ': ' . $e->getMessage());
        }
    }
}
