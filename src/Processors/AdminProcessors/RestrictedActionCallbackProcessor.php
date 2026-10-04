<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Telegram\MessageBuilders\Admin\RestrictedMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\Admin\SettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class RestrictedActionCallbackProcessor extends AbstractAdminCallbackProcessor
{
    private const string MESSAGE = RestrictedMessageBuilder::HEADER_MESSAGE;

    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $role = $this->sender->role;

        $this->answerCallbackQuery($callbackQuery, self::MESSAGE);

        if (!$role->isAdmin()) {
            $this->editSettingsMessage($callbackQuery, new RestrictedMessageBuilder()->build());

            return;
        }

        $settingsMenu = new SettingsMessageBuilder()->buildMainMenu($role);
        $this->editSettingsMessage($callbackQuery, $settingsMenu);
    }
}
