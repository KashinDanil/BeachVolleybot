<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Telegram\MessageBuilders\Admin\SettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\UserManager;

class SettingsMenuCallbackProcessor extends AbstractAdminCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $role = new UserManager()->ensureUserRecord($update->callbackQuery->from)->role;
        $message = new SettingsMessageBuilder()->buildMainMenu($role);

        $this->editSettingsMessage($update->callbackQuery, $message);
        $this->answerCallbackQuery($update->callbackQuery, '');
    }
}
