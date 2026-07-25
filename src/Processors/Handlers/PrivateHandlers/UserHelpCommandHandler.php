<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\PrivateHandlers;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UserProcessors\UserHelpCommandProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

final readonly class UserHelpCommandHandler extends AbstractDmQueueHandler
{
    public const string COMMAND = '/help';
    public const string START_COMMAND = '/start';

    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasMessage()
            && $update->message->chat->isPrivate()
            && in_array($update->message->text, [self::COMMAND, self::START_COMMAND], true);
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        return new UserHelpCommandProcessor($telegramSender);
    }
}
