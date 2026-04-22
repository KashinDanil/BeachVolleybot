<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Processors\UpdateProcessors\AbstractCallbackProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\Weather\GameWeatherLookup;
use BeachVolleybot\Weather\WeatherEnqueuer;

final class RefreshWeatherProcessor extends AbstractCallbackProcessor
{
    public const int COOLDOWN_SECONDS = 300; //5 minutes

    public function __construct(
        TelegramMessageSender $telegramSender,
        private readonly GameWeatherLookup $gameWeatherLookup = new GameWeatherLookup(),
        private readonly WeatherEnqueuer $weatherEnqueuer = new WeatherEnqueuer(),
    ) {
        parent::__construct($telegramSender);
    }

    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $inlineMessageId = $callbackQuery->inlineMessageId;

        $gameId = new GameManager()->resolveGameIdByInlineMessageId($inlineMessageId);

        if (null === $gameId) {
            $this->telegramSender->removeInlineKeyboard($inlineMessageId);
            $this->answerCallbackQuery($callbackQuery, CallbackAnswer::GAME_NOT_FOUND);

            return;
        }

        if ($this->isOnCooldown($gameId)) {
            $this->answerCallbackQuery($callbackQuery, CallbackAnswer::REFRESH_COOLDOWN);

            return;
        }

        $this->weatherEnqueuer->enqueue($gameId, force: true);
        $this->logUserAction($callbackQuery->from, 'refresh_weather', "gameId=$gameId");
        $this->answerCallbackQuery($callbackQuery, CallbackAnswer::REFRESHING_WEATHER);
    }

    private function isOnCooldown(int $gameId): bool
    {
        $game = GameFactory::tryFromGameId($gameId);
        if (null === $game) {
            return false;
        }

        $lookup = $this->gameWeatherLookup->find($game);
        if (null === $lookup) {
            return false;
        }

        return time() - $lookup->row->fetchedAt->getTimestamp() < self::COOLDOWN_SECONDS;
    }
}