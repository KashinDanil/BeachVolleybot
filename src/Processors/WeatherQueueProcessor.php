<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Telegram\InlineMessageRefresher;
use BeachVolleybot\Telegram\RateLimitedBotApi;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\Weather\Forecast\Cache\WeatherCacheUpdater;
use BeachVolleybot\Weather\Forecast\WeatherWindowResolver;
use BeachVolleybot\Weather\Location\GameLocationResolver;
use BeachVolleybot\Weather\Queue\WeatherQueuePayload;
use DanilKashin\FileQueue\Queue\QueueMessage;

final readonly class WeatherQueueProcessor implements QueueProcessorInterface
{
    public function __construct(
        private GameLocationResolver $locationResolver = new GameLocationResolver(),
        private WeatherCacheUpdater $weatherCacheUpdater = new WeatherCacheUpdater(),
        private WeatherWindowResolver $windowResolver = new WeatherWindowResolver(),
        private InlineMessageRefresher $inlineMessageRefresher = new InlineMessageRefresher(
            new TelegramMessageSender(new RateLimitedBotApi(TG_BOT_ACCESS_TOKEN, TG_MAX_REQUESTS_PER_SECOND)),
        ),
    ) {
    }

    public function process(QueueMessage $message): bool
    {
        $payload = WeatherQueuePayload::fromArray($message->payload);
        $game = GameFactory::tryFromGameId($payload->gameId, addOns: []);

        if (null === $game) {
            Logger::logVerbose('Weather fetch skipped: game gone (id=' . $payload->gameId . ')');

            return true;
        }

        $window = $this->windowResolver->windowForGame($game);

        if (empty($window->hours)) {
            return true;
        }

        $coordinates = $this->locationResolver->resolve($game)->rounded();
        $updated = $this->weatherCacheUpdater->update($coordinates, $window, $payload->force);

        if ($updated) {
            $this->inlineMessageRefresher->refresh($game->getInlineMessageId());
        }

        return true;
    }
}
