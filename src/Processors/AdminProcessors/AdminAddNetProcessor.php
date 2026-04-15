<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Game\AdminGameManager;
use BeachVolleybot\Telegram\MessageBuilders\Factories\PlayerSettingsMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AdminAddNetProcessor extends AbstractAdminGameMutationProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $gameId = $this->adminCallbackData->getGameId();
        $telegramUserId = $this->adminCallbackData->getUserId();

        $this->logAdminAction($update->callbackQuery->from, 'admin_add_net', "gameId=$gameId;userId=$telegramUserId");
        $result = new AdminGameManager()->adminAddNet($gameId, $telegramUserId);

        $this->refreshGameInlineMessage($gameId);
        $this->editSettingsMessage($update->callbackQuery, PlayerSettingsMessageFactory::build($gameId, $telegramUserId));
        $this->answerCallbackQuery($update->callbackQuery, $result->name);
    }
}
