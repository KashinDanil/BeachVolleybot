<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Game\AdminGameManager;
use BeachVolleybot\Telegram\MessageBuilders\Factories\UserSettingsMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AdminAddNetProcessor extends AbstractAdminMutationProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $gameId = $this->adminCallbackData->getGameId();
        $telegramUserId = $this->adminCallbackData->getUserId();

        $result = new AdminGameManager()->adminAddNet($gameId, $telegramUserId);
        $this->logAdminAction($update->callbackQuery->from, 'admin_add_net', "gameId=$gameId;userId=$telegramUserId");

        $this->refreshGameMessages($gameId);
        $this->editSettingsMessage($update->callbackQuery, UserSettingsMessageFactory::build($gameId, $telegramUserId));
        $this->answerCallbackQuery($update->callbackQuery, $result->name);
    }
}
