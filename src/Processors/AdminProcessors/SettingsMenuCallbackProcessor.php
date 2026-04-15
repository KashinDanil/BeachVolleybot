<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Telegram\MessageBuilders\SettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class SettingsMenuCallbackProcessor extends AbstractAdminCallbackProcessor
{
    public const string COMMAND = '/settings';

    public function process(TelegramUpdate $update): void
    {
        $message = new SettingsMessageBuilder()->buildMainMenu();

        if (null !== $update->message) {
            $this->telegramSender->sendMessage($update->message->chat->id, $message);
            $this->telegramSender->deleteMessage($update->message->chat->id, $update->message->messageId);

            return;
        }

        if (null !== $update->callbackQuery) {
            $this->editSettingsMessage($update->callbackQuery, $message);
            $this->answerCallbackQuery($update->callbackQuery, '');
        }
    }
}
