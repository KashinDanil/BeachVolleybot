<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Log\LogFileRepository;
use BeachVolleybot\Telegram\MessageBuilders\Factories\LogFileActionsMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class LogClearCallbackProcessor extends AbstractAdminCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $filename = $this->adminCallbackData->getFilename() ?? '';

        if (!LogFileRepository::isValidFilename($filename)) {
            $this->answerCallbackQuery($update->callbackQuery, LogFileRepository::INVALID_FILENAME);

            return;
        }

        new LogFileRepository()->clear($filename);

        $this->editSettingsMessage($update->callbackQuery, LogFileActionsMessageFactory::build($filename));
        $this->answerCallbackQuery($update->callbackQuery, 'Cleared');
    }
}
