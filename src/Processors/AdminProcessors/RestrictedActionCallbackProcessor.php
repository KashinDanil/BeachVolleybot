<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Telegram\MessageBuilders\Admin\AdminPanelMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\Admin\RestrictedMessageBuilder;
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
            $this->editAdminPanelMessage($callbackQuery, new RestrictedMessageBuilder()->build());

            return;
        }

        $adminPanelMessage = new AdminPanelMessageBuilder()->buildMainMenu($role);
        $this->editAdminPanelMessage($callbackQuery, $adminPanelMessage);
    }
}
