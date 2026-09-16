<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Telegram\GameMessageRefresher;
use BeachVolleybot\Telegram\RateLimitedBotApi;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\Weather\Forecast\Cache\WeatherCacheUpdater;
use BeachVolleybot\Weather\Forecast\ForecastGamesLookup;
use BeachVolleybot\Weather\Forecast\WeatherWindowResolver;
use BeachVolleybot\Weather\Queue\WeatherQueuePayload;
use DanilKashin\FileQueue\Queue\QueueMessage;

final readonly class WeatherQueueProcessor implements QueueProcessorInterface
{
    public function __construct(
        private WeatherCacheUpdater $weatherCacheUpdater = new WeatherCacheUpdater(),
        private WeatherWindowResolver $windowResolver = new WeatherWindowResolver(),
        private ForecastGamesLookup $forecastGames = new ForecastGamesLookup(),
        private GameMessageRefresher $gameMessageRefresher = new GameMessageRefresher(
            new TelegramMessageSender(new RateLimitedBotApi(TG_BOT_ACCESS_TOKEN, TG_MAX_REQUESTS_PER_SECOND)),
        ),
    ) {
    }

    public function process(QueueMessage $message): bool
    {
        $forecast = WeatherQueuePayload::fromArray($message->payload);

        if (null === $forecast) {
            Logger::logApp('Weather fetch skipped: unrecognised payload ' . json_encode($message->payload));

            return true;
        }

        $window = $this->windowResolver->windowFor($forecast->forecastTs);

        if (empty($window->hours)) {
            return true;
        }

        if ($this->weatherCacheUpdater->update($forecast->coordinates, $window)) {
            $this->gameMessageRefresher->refreshRecords($this->forecastGames->findGameRecords($forecast));
        }

        return true;
    }
}
