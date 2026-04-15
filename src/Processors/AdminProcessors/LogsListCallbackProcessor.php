<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Telegram\MessageBuilders\Factories\LogsListMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class LogsListCallbackProcessor extends AbstractAdminCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $page = $this->adminCallbackData->getPage();

        $this->editSettingsMessage($update->callbackQuery, LogsListMessageFactory::build($page));
        $this->answerCallbackQuery($update->callbackQuery, '');
    }
}
