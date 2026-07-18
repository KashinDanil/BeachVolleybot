<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Telegram\MessageBuilders\SettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\CurrentUser;

class RestrictedActionCallbackProcessor extends AbstractAdminCallbackProcessor
{
    private const string MESSAGE = 'Access restricted';

    public function process(TelegramUpdate $update): void
    {
        $role = CurrentUser::fromTelegramId($update->callbackQuery->from->id)->role();

        $this->answerCallbackQuery($update->callbackQuery, self::MESSAGE);
        $settingsMenu = new SettingsMessageBuilder()->buildMainMenu($role);
        $this->editSettingsMessage($update->callbackQuery, $settingsMenu);
    }
}
