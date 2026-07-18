<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\PrivateHandlers;

use BeachVolleybot\Processors\AdminProcessors\SettingsMenuCommandProcessor;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\CurrentUser;

final readonly class SettingsMenuCommandHandler extends AbstractDmQueueHandler
{
    public const string COMMAND = '/settings';

    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasMessage()
            && $update->message->chat->isPrivate()
            && self::COMMAND === $update->message->text
            && CurrentUser::fromTelegramId($update->message->from->id)->isAdmin();
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        return new SettingsMenuCommandProcessor($telegramSender);
    }
}
