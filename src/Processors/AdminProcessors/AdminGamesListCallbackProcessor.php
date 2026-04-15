<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Telegram\MessageBuilders\Factories\GamesListMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AdminGamesListCallbackProcessor extends AbstractAdminCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $page = $this->adminCallbackData->getPage();

        $this->editSettingsMessage($update->callbackQuery, GamesListMessageFactory::build($page));
        $this->answerCallbackQuery($update->callbackQuery, '');
    }
}
