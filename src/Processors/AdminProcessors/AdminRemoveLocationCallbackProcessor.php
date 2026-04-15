<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Game\AdminGameManager;
use BeachVolleybot\Telegram\MessageBuilders\Factories\GameDetailMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AdminRemoveLocationCallbackProcessor extends AbstractAdminGameMutationProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $gameId = $this->adminCallbackData->getGameId();

        $this->logAdminAction($update->callbackQuery->from, 'admin_remove_location', "gameId=$gameId");
        new AdminGameManager()->removeLocation($gameId);

        $this->refreshGameInlineMessage($gameId);

        $this->editSettingsMessage($update->callbackQuery, GameDetailMessageFactory::build($gameId));
        $this->answerCallbackQuery($update->callbackQuery, 'Location removed');
    }
}
