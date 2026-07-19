<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Telegram\MessageBuilders\Factories\UserRoleDetailMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AdminUserRoleDetailProcessor extends AbstractAdminCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $telegramUserId = $this->adminCallbackData->getUserId();

        $this->editSettingsMessage($update->callbackQuery, UserRoleDetailMessageFactory::build($telegramUserId));
        $this->answerCallbackQuery($update->callbackQuery, '');
    }
}
