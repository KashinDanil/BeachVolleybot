<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Telegram\MessageBuilders\Admin\AdminPanelMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AdminPanelCallbackProcessor extends AbstractAdminCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = new AdminPanelMessageBuilder()->buildMainMenu($this->sender->role);

        $this->editAdminPanelMessage($update->callbackQuery, $message);
        $this->answerCallbackQuery($update->callbackQuery, '');
    }
}
