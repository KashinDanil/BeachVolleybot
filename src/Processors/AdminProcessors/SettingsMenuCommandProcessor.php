<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\MessageBuilders\Admin\SettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\UserRecord;

class SettingsMenuCommandProcessor extends AbstractActionProcessor
{
    public function __construct(
        TelegramMessageSender $telegramSender,
        private readonly UserRecord $sender,
    ) {
        parent::__construct($telegramSender);
    }

    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;
        $settingsMessage = new SettingsMessageBuilder()->buildMainMenu($this->sender->role);

        $this->telegramSender->sendMessage($message->chat->id, $settingsMessage);
        $this->telegramSender->deleteMessage($message->chat->id, $message->messageId);
    }
}
