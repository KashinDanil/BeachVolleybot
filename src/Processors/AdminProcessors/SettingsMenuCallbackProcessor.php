<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Telegram\MessageBuilders\Admin\SettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class SettingsMenuCallbackProcessor extends AbstractAdminCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = new SettingsMessageBuilder()->buildMainMenu($this->sender->role);

        $this->editSettingsMessage($update->callbackQuery, $message);
        $this->answerCallbackQuery($update->callbackQuery, '');
    }
}
