<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\AddNetProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\AddVolleyballProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\RemoveNetProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\RemoveVolleyballProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\JoinProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\LeaveProcessor;
use BeachVolleybot\Telegram\TelegramMessageSender;

enum CallbackAction: string
{
    case Join = 'j';
    case Leave = 'l';
    case AddVolleyball = 'av';
    case RemoveVolleyball = 'rv';
    case AddNet = 'an';
    case RemoveNet = 'rn';

    public function resolveProcessor(TelegramMessageSender $telegramSender): AbstractActionProcessor
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
