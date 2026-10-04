<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\PrivateHandlers;

use BeachVolleybot\Common\Command;
use BeachVolleybot\Processors\AdminProcessors\RoleGateProcessor;
use BeachVolleybot\Processors\AdminProcessors\SettingsMenuCommandProcessor;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\Role;
use BeachVolleybot\User\UserManager;

final readonly class SettingsMenuCommandHandler extends AbstractDmQueueHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasMessage()
            && $update->message->chat->isPrivate()
            && Command::Settings->matches($update->message->text);
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        return new RoleGateProcessor(
            $telegramSender,
            new UserManager()->ensureUserRecord($update->message->from),
            Role::Admin,
            new SettingsMenuCommandProcessor($telegramSender),
        );
    }
}
