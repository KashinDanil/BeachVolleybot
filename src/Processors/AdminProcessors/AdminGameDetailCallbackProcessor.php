<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Telegram\MessageBuilders\Factories\GameDetailMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AdminGameDetailCallbackProcessor extends AbstractAdminCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = GameDetailMessageFactory::build($this->adminCallbackData->getGameId());

        $this->editSettingsMessage($update->callbackQuery, $message);
        $this->answerCallbackQuery($update->callbackQuery, '');
    }
}
