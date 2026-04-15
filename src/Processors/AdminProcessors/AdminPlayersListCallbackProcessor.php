<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Telegram\MessageBuilders\Factories\PlayersListMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AdminPlayersListCallbackProcessor extends AbstractAdminCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $gameId = $this->adminCallbackData->getGameId();
        $page = $this->adminCallbackData->getPage();

        $this->editSettingsMessage($update->callbackQuery, PlayersListMessageFactory::build($gameId, $page));
        $this->answerCallbackQuery($update->callbackQuery, '');
    }
}
