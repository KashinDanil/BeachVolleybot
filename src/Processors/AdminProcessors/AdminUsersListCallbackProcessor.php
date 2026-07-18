<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Telegram\MessageBuilders\Factories\UsersListMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AdminUsersListCallbackProcessor extends AbstractAdminCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $gameId = $this->adminCallbackData->getGameId();
        $page = $this->adminCallbackData->getPage();

        $this->editSettingsMessage($update->callbackQuery, UsersListMessageFactory::build($gameId, $page));
        $this->answerCallbackQuery($update->callbackQuery, '');
    }
}
