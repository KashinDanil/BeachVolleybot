<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Telegram\InlineMessageRefresher;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\Weather\Forecast\GameWeatherLookup\GameWeatherLookup;
use BeachVolleybot\Weather\Queue\WeatherEnqueuer;

final class RefreshWeatherProcessor extends AbstractGameCallbackProcessor
{
    public const int COOLDOWN_SECONDS = 300; //5 minutes

    public function __construct(
        TelegramMessageSender $telegramSender,
        private readonly GameWeatherLookup $gameWeatherLookup = new GameWeatherLookup(),
        private readonly WeatherEnqueuer $weatherEnqueuer = new WeatherEnqueuer(),
    ) {
        parent::__construct($telegramSender);
    }

    protected function handle(TelegramUpdate $update, GameInterface $game): void
    {
        $callbackQuery = $update->callbackQuery;

        if (GameDateTimeResolver::isKickoffPast($game->getTitle(), $game->getCreatedAt())) {
            // Self-heal the stale keyboard: rebuilding drops the refresh button via WeatherAddOn.
            new InlineMessageRefresher($this->telegramSender)->refresh($callbackQuery->inlineMessageId);
            $this->answerCallbackQuery($callbackQuery, CallbackAnswer::GAME_ALREADY_STARTED);

            return;
        }

        if ($this->isOnCooldown($game)) {
            $this->answerCallbackQuery($callbackQuery, CallbackAnswer::REFRESH_COOLDOWN);

            return;
        }

        $this->weatherEnqueuer->enqueue($game->getGameId(), force: true);
        $this->logUserAction($callbackQuery->from, 'refresh_weather', "gameId={$game->getGameId()}");
        $this->answerCallbackQuery($callbackQuery, CallbackAnswer::REFRESHING_WEATHER);
    }

    private function isOnCooldown(GameInterface $game): bool
    {
        $lookup = $this->gameWeatherLookup->find($game);

        if (null === $lookup) {
            return false;
        }

        return time() - $lookup->row->fetchedAt->getTimestamp() < self::COOLDOWN_SECONDS;
    }
}
