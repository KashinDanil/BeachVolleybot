<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Telegram\MessageBuilders\Admin\SettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\UserManager;

class RestrictedActionCallbackProcessor extends AbstractAdminCallbackProcessor
{
    private const string MESSAGE = 'Access restricted';

    public function process(TelegramUpdate $update): void
    {
        $role = new UserManager()->ensureUserRecord($update->callbackQuery->from)->role;

        $this->answerCallbackQuery($update->callbackQuery, self::MESSAGE);
        $settingsMenu = new SettingsMessageBuilder()->buildMainMenu($role);
        $this->editSettingsMessage($update->callbackQuery, $settingsMenu);
    }
}
