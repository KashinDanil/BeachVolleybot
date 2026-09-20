<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Processors\UpdateProcessors\GameAction\AddNetProcessor;
use BeachVolleybot\Processors\UpdateProcessors\GameAction\AddVolleyballProcessor;
use BeachVolleybot\Processors\UpdateProcessors\GameAction\JoinProcessor;
use BeachVolleybot\Processors\UpdateProcessors\GameAction\LeaveProcessor;
use BeachVolleybot\Processors\UpdateProcessors\GameAction\RemoveNetProcessor;
use BeachVolleybot\Processors\UpdateProcessors\GameAction\RemoveVolleyballProcessor;
use BeachVolleybot\Telegram\CallbackData\CallbackActionInterface;
use BeachVolleybot\Telegram\CallbackData\CallbackDataInterface;
use BeachVolleybot\Telegram\TelegramMessageSender;

enum GameCallbackAction: string implements CallbackActionInterface
{
    case Join = 'j';
    case Leave = 'l';
    case AddVolleyball = 'av';
    case RemoveVolleyball = 'rv';
    case AddNet = 'an';
    case RemoveNet = 'rn';

    public function resolveProcessor(TelegramMessageSender $telegramSender, ?CallbackDataInterface $callbackData = null): AbstractActionProcessor
    {
        return match ($this) {
            self::Join => new JoinProcessor($telegramSender),
            self::Leave => new LeaveProcessor($telegramSender),
            self::AddVolleyball => new AddVolleyballProcessor($telegramSender),
            self::RemoveVolleyball => new RemoveVolleyballProcessor($telegramSender),
            self::AddNet => new AddNetProcessor($telegramSender),
            self::RemoveNet => new RemoveNetProcessor($telegramSender),
        };
    }
}
