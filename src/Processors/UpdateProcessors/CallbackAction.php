<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\AddNetProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\AddVolleyballProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\RemoveNetProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\RemoveVolleyballProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\JoinProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\LeaveProcessor;
use BeachVolleybot\Telegram\MessageBuilders\DefaultTelegramMessageBuilder;
use TelegramBot\Api\BotApi;

enum CallbackAction: string
{
    case Join = 'j';
    case Leave = 'l';
    case AddVolleyball = 'av';
    case RemoveVolleyball = 'rv';
    case AddNet = 'an';
    case RemoveNet = 'rn';

    public static function fromCallbackData(?string $json): ?self
    {
        if (null === $json) {
            return null;
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return self::tryFrom($data[DefaultTelegramMessageBuilder::KEY_ACTION] ?? '');
    }

    public function resolveProcessor(BotApi $bot): AbstractActionProcessor
    {
        return match ($this) {
            self::Join => new JoinProcessor($bot),
            self::Leave => new LeaveProcessor($bot),
            self::AddVolleyball => new AddVolleyballProcessor($bot),
            self::RemoveVolleyball => new RemoveVolleyballProcessor($bot),
            self::AddNet => new AddNetProcessor($bot),
            self::RemoveNet => new RemoveNetProcessor($bot),
        };
    }
}
