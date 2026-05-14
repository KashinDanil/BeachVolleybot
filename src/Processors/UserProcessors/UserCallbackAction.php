<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UserProcessors;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\CallbackData\CallbackActionInterface;
use BeachVolleybot\Telegram\CallbackData\CallbackDataInterface;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\TelegramMessageSender;

enum UserCallbackAction: string implements CallbackActionInterface
{
    case GamesList  = 'ugl';
    case GameDetail = 'ugd';

    /**
     * @param TelegramMessageSender $telegramSender
     * @param UserCallbackData $callbackData
     *
     * @return AbstractActionProcessor
     */
    public function resolveProcessor(
        TelegramMessageSender $telegramSender,
        ?CallbackDataInterface $callbackData,
    ): AbstractActionProcessor {
        return match ($this) {
            self::GamesList  => new UserGamesListCallbackProcessor($telegramSender, $callbackData),
            self::GameDetail => new UserGameDetailCallbackProcessor($telegramSender, $callbackData),
        };
    }
}
