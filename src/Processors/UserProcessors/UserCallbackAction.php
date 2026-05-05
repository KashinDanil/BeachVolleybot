<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UserProcessors;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\TelegramMessageSender;

enum UserCallbackAction: string
{
    case GamesList  = 'ugl';
    case GameDetail = 'ugd';

    public function resolveProcessor(
        TelegramMessageSender $telegramSender,
        UserCallbackData $callbackData,
    ): AbstractActionProcessor {
        return match ($this) {
            self::GamesList  => new UserGamesListCallbackProcessor($telegramSender, $callbackData),
            self::GameDetail => new UserGameDetailCallbackProcessor($telegramSender, $callbackData),
        };
    }
}
