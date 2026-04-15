<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Telegram\MessageBuilders\Factories\PlayerSettingsMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AdminPlayerSettingsProcessor extends AbstractAdminCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $gameId = $this->adminCallbackData->getGameId();
        $telegramUserId = $this->adminCallbackData->getUserId();

        $this->editSettingsMessage($update->callbackQuery, PlayerSettingsMessageFactory::build($gameId, $telegramUserId));
        $this->answerCallbackQuery($update->callbackQuery, '');
    }
}
