<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors\Games\Users;

use BeachVolleybot\Processors\AdminProcessors\AbstractAdminCallbackProcessor;
use BeachVolleybot\Telegram\MessageBuilders\Factories\UserSettingsMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AdminUserSettingsProcessor extends AbstractAdminCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $gameId = $this->adminCallbackData->getGameId();
        $telegramUserId = $this->adminCallbackData->getUserId();

        $this->editSettingsMessage($update->callbackQuery, UserSettingsMessageFactory::build($gameId, $telegramUserId));
        $this->answerCallbackQuery($update->callbackQuery, '');
    }
}
