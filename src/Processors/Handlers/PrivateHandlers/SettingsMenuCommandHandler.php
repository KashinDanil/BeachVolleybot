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
use BeachVolleybot\User\UserRecord;

final readonly class SettingsMenuCommandHandler extends AbstractDmQueueHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasMessage()
            && $update->message->chat->isPrivate()
            && Command::Settings->matches($update->message->text);
    }

    protected function createSenderProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
        UserRecord $sender,
    ): AbstractActionProcessor {
        return new RoleGateProcessor(
            $telegramSender,
            $sender,
            Role::Admin,
            new SettingsMenuCommandProcessor($telegramSender, $sender),
        );
    }
}
