<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Game\AdminGameManager;
use BeachVolleybot\Game\LeaveResult;
use BeachVolleybot\Telegram\MessageBuilders\Factories\GameDetailMessageFactory;
use BeachVolleybot\Telegram\MessageBuilders\Factories\PlayerSettingsMessageFactory;
use BeachVolleybot\Telegram\MessageBuilders\Factories\PlayersListMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AdminRemoveSlotProcessor extends AbstractAdminGameMutationProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $gameId = $this->adminCallbackData->getGameId();
        $telegramUserId = $this->adminCallbackData->getUserId();

        $this->logAdminAction($update->callbackQuery->from, 'admin_remove_slot', "gameId=$gameId;userId=$telegramUserId");
        $gameManager = new AdminGameManager();
        $result = $gameManager->leaveGame($gameId, $telegramUserId);

        if (LeaveResult::NotJoined === $result) {
            $this->answerCallbackQuery($update->callbackQuery, 'No slots to remove');
            $this->editSettingsMessage($update->callbackQuery, GameDetailMessageFactory::build($gameId));

            return;
        }

        $this->refreshGameInlineMessage($gameId);

        if ($gameManager->isPlayerInGame($gameId, $telegramUserId)) {
            $this->editSettingsMessage($update->callbackQuery, PlayerSettingsMessageFactory::build($gameId, $telegramUserId));
        } else {
            $this->editSettingsMessage($update->callbackQuery, PlayersListMessageFactory::build($gameId, 1));
        }

        $this->answerCallbackQuery($update->callbackQuery, 'Slot removed');
    }
}
