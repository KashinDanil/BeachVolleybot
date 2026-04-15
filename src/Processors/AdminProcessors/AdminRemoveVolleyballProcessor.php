<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Game\AdminGameManager;
use BeachVolleybot\Telegram\MessageBuilders\Factories\PlayerSettingsMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AdminRemoveVolleyballProcessor extends AbstractAdminGameMutationProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $gameId = $this->adminCallbackData->getGameId();
        $telegramUserId = $this->adminCallbackData->getUserId();

        $result = new AdminGameManager()->removeVolleyball($gameId, $telegramUserId);

        $this->refreshGameInlineMessage($gameId);
        $this->editSettingsMessage($update->callbackQuery, PlayerSettingsMessageFactory::build($gameId, $telegramUserId));
        $this->answerCallbackQuery($update->callbackQuery, $result->name);
    }
}
