<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\AdminProcessors;

use BeachVolleybot\Log\LogFileRepository;
use BeachVolleybot\Telegram\MessageBuilders\Factories\LogTailMessageFactory;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class LogTailCallbackProcessor extends AbstractAdminCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $filename = $this->adminCallbackData->getFilename() ?? '';

        if (!LogFileRepository::isValidFilename($filename)) {
            $this->answerCallbackQuery($update->callbackQuery, LogFileRepository::INVALID_FILENAME);

            return;
        }

        $this->editSettingsMessage($update->callbackQuery, LogTailMessageFactory::build($filename));
        $this->answerCallbackQuery($update->callbackQuery, '');
    }
}
