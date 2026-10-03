<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\MessageBuilders\Admin\SettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\UserManager;

class SettingsMenuCommandProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;

        $role = new UserManager()->ensureUserRecord($message->from)->role;
        $settingsMessage = new SettingsMessageBuilder()->buildMainMenu($role);

        $this->telegramSender->sendMessage($message->chat->id, $settingsMessage);
        $this->telegramSender->deleteMessage($message->chat->id, $message->messageId);
    }
}
